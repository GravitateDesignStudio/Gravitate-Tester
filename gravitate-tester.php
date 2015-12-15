<?php
/*
Plugin Name: Gravitate Tester
Description: Allows to run Tests in the WP environment
Version: 1.0.0
Plugin URI: http://www.gravitatedesign.com
Author: Gravitate

*/

register_activation_hook( __FILE__, array( 'GRAVITATE_TESTER', 'activate' ));
register_deactivation_hook( __FILE__, array( 'GRAVITATE_TESTER', 'deactivate' ));

add_action('admin_menu', array( 'GRAVITATE_TESTER', 'admin_menu' ));
add_action('init', array( 'GRAVITATE_TESTER', 'init' ));
add_filter('plugin_action_links_'.plugin_basename(__FILE__), array('GRAVITATE_TESTER', 'plugin_settings_link' ));
add_action('wp_ajax_grav_run_test', array( 'GRAVITATE_TESTER', 'ajax_run_test' ));


class GRAVITATE_TESTER {

	private static $version = '1.0.0';
	private static $settings = array();
	private static $page = 'options-general.php?page=gravitate_tester';
	private static $option_key = 'gravitate_tester_settings';

	public static function init()
	{
		if(is_admin() || !empty($_GET['grav_js_test']))
		{
			self::setup();
		}

		if(!empty($_GET['grav_js_test']))
		{
			$test = $_GET['grav_js_test'];
			$enabled_tests = self::get_enabled_tests();
			if(in_array($test, $enabled_tests))
			{
				$tests = self::get_tests();
				if(!empty($tests[$test]['class']))
				{
					$test_class = $tests[$test]['class'];
					$test_obj = new $test_class();
					$id = dechex(crc32($tests[$test]['file']));
					$test_obj->id = $id;

					if(method_exists($test_obj,'js_head'))
					{
						add_action('wp_head', array($test_obj, 'js_head'));
					}

					if(method_exists($test_obj,'js_footer'))
					{
						add_action('wp_footer', array($test_obj, 'js_footer'));
					}
				}
			}
		}
	}

	private static function setup()
	{
		include plugin_dir_path( __FILE__ ).'gravitate-plugin-settings.php';
		new GRAV_TESTER_PLUGIN_SETTINGS(self::$option_key);
		self::get_settings(true);
	}

	/**
	 * Runs on WP Plugin Activation
	 *
	 * @return void
	 */
	public static function activate()
	{
		// Set Default Settings
		if(!get_option(self::$option_key))
		{
			$default_settings = array(
				'php_tests' => array('wp_head_footer', 'php_empty_space'),
				'wp_tests' => array('wp_debug'),
			);

			update_option(self::$option_key, $default_settings);
		}
	}

	/**
	 * Grabs the settings from the Settings class
	 *
	 * @param boolean $force
	 *
	 * @return void
	 */
	public static function get_settings($force=false)
	{
		self::$settings = GRAV_TESTER_PLUGIN_SETTINGS::get_settings($force);
	}

	/**
	 * Create the Admin Menu in that Admin Panel
	 *
	 * @return void
	 */
	public static function admin_menu()
	{
		add_submenu_page( 'options-general.php', 'Gravitate Tester', 'Gravitate Tester', 'manage_options', 'gravitate_tester', array( __CLASS__, 'admin' ));
	}

	public static function plugin_settings_link($links)
	{
		$settings_link = '<a href="options-general.php?page=gravitate_tester">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	public static function get_tests()
	{
		$grav_tests = array();

		foreach (glob(plugin_dir_path( __FILE__ ).'grav_tests/*.php') as $file)
		{
			$grav_test[] = $file;
		}

		$grav_test = apply_filters( 'grav_tests', $grav_test );

		$tests = array();

		foreach ($grav_test as $file)
		{
			if(file_exists($file))
			{
				include_once($file);
				$classes = get_declared_classes();
				$test_class = end($classes);

				$test = new $test_class();
				$id = dechex(crc32($file));

				$tests[$id] = array('id' => $id, 'type' => $test->type(), 'group' => $test->group(), 'urls' => '', 'file' => $file, 'class' => $test_class, 'description' => $test->description());

				if($test->type() === 'js' && method_exists($test,'js_urls'))
				{
					$tests[$id]['urls'] = $test->js_urls();
				}
			}
		}

		return $tests;
	}

	private static function get_enabled_tests()
	{
		self::get_settings();

		$tests = array();

		foreach (self::$settings as $setting_key => $setting)
		{
			if(strpos($setting_key, '_grav_tests') && is_array($setting))
			{
				$tests = array_merge($tests, $setting);
			}
		}

		return $tests;
	}


	public static function get_general_page_urls()
	{
		$urls = array(site_url(),site_url().'/?s=grav-test',site_url().'/404-grav-test-url');

		if($menus = get_registered_nav_menus())
		{
			foreach ($menus as $menu => $title)
			{
				$locations = get_nav_menu_locations();

				if(isset($locations[ $menu ]))
				{
					$menu = wp_get_nav_menu_object( $locations[ $menu ] );

					if(!empty($menu->term_id))
					{
						$items = wp_get_nav_menu_items( $menu->term_id );

						if(!empty($items))
						{
							foreach ($items as $item)
							{
								if(strpos($item->url, site_url()) !== false)
								{
									$urls[] = $item->url;
								}
							}
						}
					}
				}

				if(empty($items))
				{
					$menu = wp_page_menu( array('echo' => false) );
					preg_match_all('/href\=\"([^"]*)\"/s', $menu, $matches);

					if(!empty($matches[1]))
					{
						foreach ($matches[1] as $url)
						{
							$urls[] = $url;
						}
					}
				}
			}
		}

		if(count($urls) < 2)
		{
			foreach(get_pages(array('number' => 10)) as $page)
			{
				$urls[] = get_permalink($page->ID);
			}
		}

		foreach(get_posts(array('posts_per_page' => 2)) as $post)
		{
			$urls[] = get_permalink($post->ID);
		}

		return array_unique($urls);
	}

	/**
     * Returns the Settings Fields for specifc location.
     *
     * @param string $location
     *
     * @return array
     */
	private static function get_settings_fields($location = 'general')
	{
		switch ($location)
		{

			case 'advanced':
				$fields['search_options'] = array('type' => 'checkbox', 'label' => 'Search Settings', 'options' => $search_options, 'description' => '');

			break;

			default:
			case 'general':


				$tests = self::get_tests();

				$groups = array();

				foreach ($tests as $test_id => $test)
				{
					if(empty($groups[$test['group']]))
					{
						$groups[$test['group']] = array();
					}
					$groups[$test['group']][$test['id']] = $test['description'];
				}

				$fields = array();


				foreach ($groups as $group => $tests)
				{
					$fields[sanitize_title($group).'_grav_tests'] = array('type' => 'checkbox', 'label' => $group, 'options' => $tests);
				}

			break;

		}

		return $fields;
	}

	/**
	 * Gets current tab and sets active state
	 *
	 * @param string $current
	 * @param string $section
	 *
	 * @return
	 */
	public static function get_current_tab($current = '' , $section = ''){

		if($current == $section || ($current == '' && $section == 'general'))
		{
			return 'active';
		}

	}

	/**
	 * Runs the Admin Page and outputs the HTML
	 *
	 * @return void
	 */
	public static function admin()
	{
		// Get Settings
		self::get_settings(true);

		// Save Settings if POST
		$response = GRAV_TESTER_PLUGIN_SETTINGS::save_settings();

		if($response['error'])
		{
			$error = 'Error saving Settings. Please try again.';
		}
		else if($response['success'])
		{
			$success = 'Settings saved successfully.';
		}

		?>

		<div class="wrap">
			<header>
				<h1>Gravitate Tester</h1>
			</header>
			<main>
				<h4>Version <?php echo self::$version;?></h4>

				<?php if(!empty($error)){?><div class="error"><p><?php echo $error; ?></p></div><?php } ?>
				<?php if(!empty($success)){?><div class="updated"><p><?php echo $success; ?></p></div><?php } ?>
			</main>
		<br>

		<div class="gravitate-redirects-page-links">
			<a href="<?php echo self::$page;?>&section=settings" class="<?php echo self::get_current_tab($_GET['section'], 'settings'); ?>">Settings</a>
			<a href="<?php echo self::$page;?>&section=run_tests" class="<?php echo self::get_current_tab($_GET['section'], 'run_tests'); ?>">Run Tests</a>
			<a href="<?php echo self::$page;?>&section=developers" class="<?php echo self::get_current_tab($_GET['section'], 'developers'); ?>">Developers</a>
		</div>


		<br style="clear:both;">
		<br>

		<?php

		$section = (!empty($_GET['section']) ? $_GET['section'] : 'settings');

		switch($section)
		{
			case 'run_tests':
				self::run_tests();
			break;

			case 'developers':
				self::developers();
			break;

			default:
			case 'settings':
				self::form();
			break;
		}
		?>
		</div>
		<?php
	}

	/**
	 * Outputs the Form with the correct fields
	 *
	 * @param string $location
	 *
	 * @return type
	 */
	private static function form($location = 'general')
	{
		// Get Form Fields
		switch ($location)
		{
			default;
			case 'general':
				$fields = self::get_settings_fields();
				break;

			case 'advanced':
				$fields = self::get_settings_fields('advanced');
				break;
		}

		GRAV_TESTER_PLUGIN_SETTINGS::get_form($fields);
	}



	private static function run_tests()
	{
		self::get_settings();

		$tests = self::get_tests();
		$enabled_tests = self::get_enabled_tests();

		?>

		<style>
		.passed {
			color: #0A0;
		}
		.failed {
			color: #A00;
		}
		.testing {
			color: #999;
		}
		#the-list td input {
			display: block;
			border: 1px dashed #c8c8c8;
			padding: 0 6px;
			background-color: #fcfcfc;
			margin-top: 3px;
			font-size: 0.7rem;
			cursor: text;
			width: 100%;
		}
		</style>

		<div style="text-align:right;">
			<button onclick="run_all_tests();" class="button button-primary">Run All Tests</a>
		</div>
		<br>

		<table class="wp-list-table widefat plugins" cellspacing="0">
			<thead>
				<tr>
					<th class="manage-column column-cb" id="cb" scope="col">
						Test
					</th>
					<th style="" class="manage-column column-description" id="description" scope="col">
						Status
					</th>
					<th style="" class="manage-column column-description" scope="col">

					</th>
				</tr>
			</thead>

			<tbody id="the-list">
				<?php foreach ($enabled_tests as $test) { ?>
					<?php if(!empty($tests[$test])) { ?>
					<tr class="event active">
						<td style="width:40%;">
							<?php echo $tests[$test]['description']; ?>
						</td>
						<td style="width:50%;" class="test-<?php echo $tests[$test]['id']; ?>">
							<h4 style="margin:0;"></h4>
							<span></span><input readonly="readonly">
						</td>
						<td style="width:10%;white-space:nowrap;">
							<button class="button" onclick="run_ajax_test('<?php echo $tests[$test]['id']; ?>', '<?php echo $tests[$test]['type']; ?>', '<?php echo $tests[$test]['urls']; ?>', 500);">Run Test</button>
						</td>
					</tr>
					<?php } ?>
				<?php } ?>
			</tbody>
		</table>

		<script>

		jQuery('#the-list td input').hide();

		var grav_tests = [];
		var grav_js_tests_failed = [];
		<?php foreach ($enabled_tests as $num => $test) { ?>
			<?php if(!empty($tests[$test])) { ?>
				grav_tests[<?php echo $num;?>] = {'id': '<?php echo $tests[$test]['id'];?>', 'type': '<?php echo $tests[$test]['type'];?>', 'urls': '<?php echo $tests[$test]['urls'];?>'};
			<?php } ?>
			<?php if(!empty($tests[$test]['type']) && $tests[$test]['type'] === 'js') { ?>
				grav_js_tests_failed['<?php echo $tests[$test]['id'];?>'] = false;
			<?php } ?>
		<?php } ?>

		function run_all_tests()
		{
			for(var t in grav_tests)
			{
				console.log(grav_tests[t]['id']);
				run_ajax_test(grav_tests[t]['id'], grav_tests[t]['type'], grav_tests[t]['urls'], 300);
			}
		}

		function run_ajax_test(test, type, urls, msec)
		{
			jQuery('.test-' + test + ' h4').removeClass('failed').removeClass('passed').addClass('testing').html('Testing...');

			jQuery('.test-' + test + ' span').html('');
			jQuery('.test-' + test + ' input').hide();
			jQuery('.test-' + test + ' input').val('');

			setTimeout(function(){

				if(type === 'php')
				{
					jQuery.post('<?php echo admin_url("admin-ajax.php");?>',
					{
						'action': 'grav_run_test',
						'grav_test': test
					},
					function(response)
					{
						test_results(test, response);

					}).fail(function()
					{
	    				jQuery('.test-' + test + ' h4').removeClass('passed').removeClass('failed').removeClass('testing').html('Unknown');
						jQuery('.test-' + test + ' span').html('Error Getting Response from Test.');
	  				});
	  			}
	  			else if(type === 'js')
	  			{
	  				if(urls)
	  				{
	  					urls = urls.split(' ').join('').split(',');
	  					var url;
	  					var url_id;
	  					for(var u in urls)
	  					{
	  						url = urls[u];
	  						url_id = 'frame-'+test+'-'+u;

	  						if(jQuery('#'+url_id).length)
	  						{
	  							jQuery('#'+url_id).remove();
	  						}
	  						jQuery('<iframe id="'+url_id+'" src="'+url+(url.indexOf('?') > -1 ? '&' : '?')+'grav_js_test='+test+'">').appendTo('body').css('display', 'none');
	  					}
	  				}
	  			}
			}, msec);
		}

		function test_results(test, response)
		{
			var data = false;

			if(response)
			{
				if(typeof response === 'string')
				{
					if(response.substr(0, 1) === '{')
					{
						data = jQuery.parseJSON(response);
					}
					else if(response.indexOf('{') > -1)
					{
						response = response.substr(response.indexOf('{'));
						data = jQuery.parseJSON(response);
					}
				}
				else
				{
					data = response;
				}

				if(data)
				{
					if(data['pass'] === true)
					{
						jQuery('.test-' + test + ' h4').addClass('passed').removeClass('failed').removeClass('testing').html('Passed');
					}
					else if(data['pass'] === false)
					{
						jQuery('.test-' + test + ' h4').addClass('failed').removeClass('passed').removeClass('testing').html('Failed');
					}
					else
					{
						jQuery('.test-' + test + ' h4').removeClass('failed').removeClass('passed').removeClass('testing').html('Unknown');
					}

					if(data['message'])
					{
						jQuery('.test-' + test + ' span').html(data['message']);
					}

					if(data['location'])
					{
						jQuery('.test-' + test + ' input').val(data['location']);
						jQuery('.test-' + test + ' input').show();
					}
				}
				else
				{
					jQuery('.test-' + test + ' h4').removeClass('passed').removeClass('failed').removeClass('testing').html('Unknown');
					jQuery('.test-' + test + ' span').html('Error Getting Response from Test.');
				}
			}
		}

		function grav_tests_js_pass(test, pass, message, location)
		{
			if(pass && grav_js_tests_failed[test] === true)
			{
				return;
			}

			if(!pass && grav_js_tests_failed[test] === false)
			{
				grav_js_tests_failed[test] = true;
			}
			test_results(test, {'pass': pass, 'message': message.replace('?grav_js_test='+test, '').replace('&grav_js_test='+test, ''), 'location': location.replace('?grav_js_test='+test, '').replace('&grav_js_test='+test, '')});
		}

		</script>

		<?php
	}

	public static function ajax_run_test()
	{
		if(!empty($_POST['grav_test']) && is_user_logged_in() && current_user_can('manage_options'))
		{
			$tests = self::get_tests();
			$enabled_tests = self::get_enabled_tests();

			if(!empty($tests[$_POST['grav_test']]))
			{
				$test = $tests[$_POST['grav_test']];
				$test_class = $test['class'];

				$test_obj = new $test_class();
				$response = $test_obj->run();
				if(!empty($response))
				{
					echo json_encode($response);
					exit;
				}
			}
		}
		else
		{
			echo 'Error: You Must be logged in and have the correct permissions to do this.';
		}
		exit;
	}


	private static function developers()
	{
		?>
		<div class="grav-blocks-developers">
			<h2>You can add your own Tests by using the 'grav_tests' filter.</h2>
			<h3>grav_tests</h3>
				This filters through the available tests.
				<blockquote>
				<label>Adding Your Test</label>
				<br>
				<textarea class="grav-code-block" rows="9" cols="80">
add_filter( 'grav_tests', 'your_function' );
function your_function($tests)
{
	$tests[] = 'path/to/your/test/file/class.php';
	return $tests;
}
				</textarea>
				</blockquote>
				<blockquote>
				<label>Your Test class.php file</label>
				<br>
				<textarea class="grav-code-block" rows="9" cols="80">
&lt;?php

class YourCompanyNameCustomTestName
{
	public function type()
	{
		return 'php'; /* php | js */
	}

	public function group()
	{
		return 'WordPress Tests';
	}

	public function description()
	{
		return 'Your Description of the Test Here';
	}

	public function run()
	{
		if(true)
		{
			return array('pass' => true, 'message' => 'Your Test Passed', 'location' => '');
		}
		else if(false)
		{
			return array('pass' => false, 'message' => 'Your Test Failed', 'location' => 'somefile.php:32');
		}
		else
		{
			return array('pass' => null, 'message' => 'Unknown Error', 'location' => '');
		}
	}
}

				</textarea>
				</blockquote>
				<br>
				<blockquote>
				<label>Example of a Javascript Test</label>
				<br>
				<textarea class="grav-code-block" rows="9" cols="80">
&lt;?php

class YourCompanyNameCustomTestName
{
	public function type()
	{
		return 'js';
	}

	public function group()
	{
		return 'JS Tests';
	}

	public function description()
	{
		return 'Check Basic Pages for JS Errors on Page Load';
	}

	public function js_urls()
	{
		$urls = GRAVITATE_TESTER::get_general_page_urls();

		return implode(',', $urls);
	}

	public function js_head()
	{
		?&gt;
		&lt;script type="text/javascript"&gt;

			window.onerror = function(error, file, linenumber)
			{
		  		parent.grav_tests_js_pass('&lt;?php echo $this->id;?&gt;', false, 'JS Error loading ('+window.location.href+'): '+ error, file+' (Line: '+linenumber+')');
			};

		&lt;/script&gt;
		&lt;?php
	}

	public function js_footer()
	{
		?&gt;
		&lt;script type="text/javascript"&gt;
		// Some Code Here
		&lt;/script&gt;
		&lt;?php
	}
}

				</textarea>
				</blockquote>
		</div>
		<?php

	}

}



