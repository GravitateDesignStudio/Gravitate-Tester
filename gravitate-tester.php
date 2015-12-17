<?php
/*
Plugin Name: Gravitate Automated Tester
Description: Allows to run Automated Tests in the WP Admin Panel
Version: 1.0.0
Plugin URI: http://www.gravitatedesign.com
Author: Gravitate

*/

register_activation_hook( __FILE__, array( 'GRAV_TESTS', 'activate' ));
register_deactivation_hook( __FILE__, array( 'GRAV_TESTS', 'deactivate' ));

add_action('admin_menu', array( 'GRAV_TESTS', 'admin_menu' ));
add_action('init', array( 'GRAV_TESTS', 'init' ));
add_filter('plugin_action_links_'.plugin_basename(__FILE__), array('GRAV_TESTS', 'plugin_settings_link' ));
add_action('wp_ajax_grav_run_test', array( 'GRAV_TESTS', 'ajax_run_test' ));
add_action('wp_ajax_grav_run_fix_test', array( 'GRAV_TESTS', 'ajax_run_fix_test' ));


class GRAV_TESTS {

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
					$id = sanitize_title($tests[$test]['label']).'-'.dechex(crc32($tests[$test]['file']));
					$test_obj->id = $id;

					if(method_exists($test_obj,'js_head'))
					{
						add_action('wp_head', array($test_obj, 'js_head'), 0);

					}

					if(method_exists($test_obj,'js_footer'))
					{
						add_action('wp_footer', array($test_obj, 'js_footer'));
					}

					if(!empty($_GET['grav_js_remove_admin_bar']))
					{
						add_filter('show_admin_bar', '__return_false');

						foreach ($_COOKIE as $cookie_key => $cookie_value)
						{
							if($cookie_key !== 'wordpress_test_cookie' && strpos($cookie_key, 'wordpress_') !== false)
							{
								unset($_COOKIE[$cookie_key]);
							}
						}
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
				$id = sanitize_title($test->label()).'-'.dechex(crc32($file));

				$tests[$id] = array('id' => $id, 'type' => $test->type(), 'environment' => 'all', 'group' => $test->group(), 'can_fix' => false, 'js_urls' => "''", 'file' => $file, 'class' => $test_class, 'label' => $test->label(), 'description' => $test->description());

				if($test->type() === 'js' && method_exists($test,'js_urls'))
				{
					$tests[$id]['js_urls'] = stripslashes(json_encode($test->js_urls()));
				}

				if(method_exists($test,'environment'))
				{
					$tests[$id]['environment'] = $test->environment();
				}

				if(method_exists($test,'can_fix') && method_exists($test,'fix'))
				{
					if($test->can_fix())
					{
						$tests[$id]['can_fix'] = true;
					}
				}
			}
		}

		ksort($tests);

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

		sort($tests);

		return $tests;
	}


	public static function remove_comments($contents='')
	{
		$contents = preg_replace('!/\*.*?\*/!s', '', $contents);
		$contents = preg_replace('/\n\s*\n/', "\n", $contents);
		$contents = preg_replace('![ \t]*//.*[ \t]*[\r\n]!', '', $contents);

		return $contents;
	}


	public static function get_general_page_urls()
	{
		$site_url = site_url('/');
		$urls = array($site_url,$site_url.'?s=grav-test',$site_url.'404-grav-test-url');

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
								if(strpos($item->url, site_url()) !== false && count($urls) <= 10)
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
							if(count($urls) <= 10)
							{
								$urls[] = $url;
							}
						}
					}
				}
			}
		}

		if(count($urls) <= 10)
		{
			foreach(get_pages(array('number' => 2)) as $page)
			{
				$urls[] = get_permalink($page->ID);
			}
		}

		foreach(get_posts(array('posts_per_page' => 1)) as $post)
		{
			$urls[] = get_permalink($post->ID);
		}

		$args = array('public' => true, '_builtin' => false);

		if($custom_post_types = get_post_types(array('public' => true, '_builtin' => false)))
		{
			foreach ($custom_post_types as $post_type)
			{
				foreach(get_posts(array('post_type' => $post_type, 'posts_per_page' => 1)) as $post)
				{
					$urls[] = get_permalink($post->ID);
				}
			}
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
				<h1>Gravitate Automated Tester</h1>
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


	public static function guess_environment()
	{

		$ip_sub = substr(self::get_real_ip(), 0, 3);

		if($ip_sub == '127')
		{
			return 'local';
		}

		if($ip_sub === '10.' || $ip_sub === '192')
		{
			return 'dev';
		}

		if(count(explode('.',$_SERVER['HTTP_HOST'])) > 2 && strpos($_SERVER['HTTP_HOST'], 'www.') === false)
		{
			return 'staging';
		}

		if(count(explode('.',$_SERVER['HTTP_HOST'])) === 2 || strpos($_SERVER['HTTP_HOST'], 'www.') !== false)
		{
			return 'production';
		}

		return 'dev';
	}


	/**
	 * Returns the Real IP from the user
	 *
	 * @return string
	 */
	public static function get_real_ip()
    {
        foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR') as $server_ip)
        {

            if(!empty($_SERVER[$server_ip]) && is_string($_SERVER[$server_ip]))
            {
            	$ips = explode(',', $_SERVER[$server_ip]);
                if($ip = trim(reset($ips)))
	            {
	            	return $ip;
	            }
            }
        }

        return $_SERVER['REMOTE_ADDR'];
    }



	private static function run_tests()
	{
		self::get_settings();

		$tests = self::get_tests();
		$enabled_tests = self::get_enabled_tests();

		$environments = array('all', 'local', 'dev', 'staging', 'production');

		$environment_default = self::guess_environment();

		if($_SERVER['REMOTE_ADDR'] === '127.0.0.1')
		{

		}

		foreach ($tests as $test)
		{
			$environments = array_merge($environments, explode(',', $test['environment']));
		}

		$environments = array_unique($environments);

		unset($environments[array_search('all', $environments)]);

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
		#the-list td .fix-button {
			display: none;
		}
		#the-list th.info {
			width:40%;
			padding-left: 12px;
			color: #999;
			padding: 10px 9px;
		}
		#the-list th.info h4 {
			color: #1B5D8A;
			font-weight: bold;
		}
		#the-list tr.inactive th {
			border-left-color: #DDD;
		}
		#the-list tr.inactive th, #the-list tr.inactive td {
			background-color: #fcfcfc;
		}
		#the-list tr.inactive th h4, #the-list tr.inactive th {
			color: #BBB;
		}
		#the-list td.status {
			width:50%;
		}
		#the-list td.actions {
			width:10%;
			white-space:nowrap;
			text-align:right;
		}
		</style>


		<div style="text-align:left; width:50%;">
			<label>Environment</label>
			<select id="environment" autocomplete="off">
				<?php foreach($environments as $environment){ ?>
					<option <?php selected($environment_default, $environment);?>><?php echo $environment;?></option>
				<?php } ?>
			</select> &nbsp;
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
				<?php foreach ($enabled_tests as $num => $test) { ?>
					<?php if(!empty($tests[$test])) { ?>
					<tr class="event active test-<?php echo $tests[$test]['id']; ?> environment-<?php echo implode(' environment-', explode(',', $tests[$test]['environment']));?>">
						<th class="info check-column">
							<h4 style="margin:0;"><?php echo $tests[$test]['label']; ?></h4>
							<?php echo $tests[$test]['description']; ?>
						</td>
						<td class="status">
							<h4 style="margin:0;"></h4>
							<span></span><input readonly="readonly">
						</td>
						<td class="actions">
							<?php if($tests[$test]['can_fix']){ ?>
								<button class="button fix-button" onclick="run_ajax_fix('<?php echo $tests[$test]['id']; ?>');">Fix</button>
							<?php } ?>
							<button class="button" onclick="run_ajax_test('<?php echo $tests[$test]['id']; ?>', 500);">Run Test</button>
						</td>
					</tr>
					<?php } ?>
				<?php } ?>
			</tbody>
		</table>

		<script>

		jQuery('#the-list td input').hide();

		function update_environments(val)
		{
			if(val === 'all')
			{
				jQuery('#the-list tr').css('opacity', 1).removeClass('inactive');
			}
			else
			{
				jQuery('#the-list tr').addClass('inactive');
				jQuery('#the-list tr.environment-all, #the-list tr.environment-'+val).removeClass('inactive');
			}
		}

		jQuery('#environment').on('change', function()
		{
			update_environments(jQuery(this).val());
		});

		update_environments(jQuery('#environment').val());

		var grav_tests = [];
		<?php foreach ($enabled_tests as $num => $test) { ?>
			<?php if(!empty($tests[$test])) { ?>
			grav_tests['<?php echo $tests[$test]['id'];?>'] = {'id': '<?php echo $tests[$test]['id'];?>', 'type': '<?php echo $tests[$test]['type'];?>', 'environment': '<?php echo $tests[$test]['environment'];?>', 'js_urls': <?php echo $tests[$test]['js_urls'];?>};
			<?php } ?>
		<?php } ?>

		var grav_js_tests_failed = [];
		<?php foreach ($enabled_tests as $num => $test) { ?>
			<?php if(!empty($tests[$test]['type']) && $tests[$test]['type'] === 'js') { ?>
			grav_js_tests_failed['<?php echo $tests[$test]['id'];?>'] = false;
			<?php } ?>
		<?php } ?>

		function run_all_tests()
		{
			var environment = jQuery('#environment').val();
			var num = 1;
			for(var t in grav_tests)
			{
				if(grav_tests[t]['environment'] === 'all' || grav_tests[t]['environment'].indexOf(environment) > -1)
				{
					run_ajax_test(grav_tests[t]['id'], (500*num));
				}
				num++;
			}
		}

		function run_ajax_fix(test)
		{
			jQuery.post('<?php echo admin_url("admin-ajax.php");?>',
			{
				'action': 'grav_run_fix_test',
				'grav_test': test
			},
			function(response)
			{
				test_results(test, response);

			}).fail(function()
			{
				jQuery('.test-' + test + ' .status h4').removeClass('passed').removeClass('failed').removeClass('testing').html('Unknown');
				jQuery('.test-' + test + ' .status span').html('Error Getting Response from Fix.');
			});
		}

		function run_ajax_test(test, msec)
		{
			if(grav_tests[test] !== 'undefined')
			{
				jQuery('.test-' + test + ' .status h4').removeClass('failed').removeClass('passed').addClass('testing').html('Testing...');

				jQuery('.test-' + test + ' .status span').html('');
				jQuery('.test-' + test + ' .status input').hide();
				jQuery('.test-' + test + ' .status input').val('');
				jQuery('.test-' + test + ' .actions .fix-button').hide();

				setTimeout(function(){

					if(grav_tests[test]['type'] === 'php')
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
		    				jQuery('.test-' + test + ' .status h4').removeClass('passed').removeClass('failed').removeClass('testing').html('Unknown');
							jQuery('.test-' + test + ' .status span').html('Error Getting Response from Test.');
		  				});
		  			}
		  			else if(grav_tests[test]['type'] === 'js')
		  			{
		  				if(grav_tests[test]['js_urls'])
		  				{
		  					var urls = grav_tests[test]['js_urls'];
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
		  						load_js_test_frame(test, url_id, url, u);
		  					}
		  				}
		  			}
				}, msec);
			}
		}

		function load_js_test_frame(test, url_id, url, sec)
		{
			var src = (url['url'] !== 'undefined' ? url['url'] : '');
			var width = (url['width'] !== 'undefined' ? url['width'] : '800');
			var height = (url['height'] !== 'undefined' ? url['height'] : '600');
			var admin_bar = (url['with_admin_bar'] !== 'undefined' ? url['with_admin_bar'] : false);

			if(src)
			{
				setTimeout(function(){
					if(!grav_js_tests_failed[test])
					{
						jQuery('<div class="iframe-container-'+test+'" id="'+url_id+'" style="width:0;height:0;overflow:hidden;"><iframe width="'+width+'" height="'+height+'" name="'+url_id+'" src="'+src+(src.indexOf('?') > -1 ? '&' : '?')+'grav_js_test='+test+(!admin_bar ? '&grav_js_remove_admin_bar=1' : '')+'"></div>').appendTo('body').css('visibility', 'hidden');
					}
				}, 500*sec);
			}
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
						jQuery('.test-' + test + ' .status h4').addClass('passed').removeClass('failed').removeClass('testing').html('Passed');
						jQuery('.test-' + test + ' .actions .fix-button').css('display', 'none');
					}
					else if(data['pass'] === false)
					{
						jQuery('.test-' + test + ' .status h4').addClass('failed').removeClass('passed').removeClass('testing').html('Failed');
						jQuery('.test-' + test + ' .actions .fix-button').css('display', 'inline-block');
					}
					else
					{
						jQuery('.test-' + test + ' .status h4').removeClass('failed').removeClass('passed').removeClass('testing').html('Unknown');
						jQuery('.test-' + test + ' .actions .fix-button').css('display', 'none');
					}

					if(data['message'])
					{
						jQuery('.test-' + test + ' .status span').html(data['message']);
					}

					if(data['location'])
					{
						jQuery('.test-' + test + ' .status input').val(data['location']);
						jQuery('.test-' + test + ' .status input').show();
					}
				}
				else
				{
					jQuery('.test-' + test + ' .status h4').removeClass('passed').removeClass('failed').removeClass('testing').html('Unknown');
					jQuery('.test-' + test + ' .status span').html('Error Getting Response from Test.');
				}
			}
		}

		function grav_tests_js_pass(test, pass, message, location)
		{
			/* If already Failed Test then ignore all other responses */
			if(grav_js_tests_failed[test] === true)
			{
				return;
			}

			if(!pass && grav_js_tests_failed[test] === false)
			{
				grav_js_tests_failed[test] = true;
				jQuery('.iframe-container-'+test).remove();
				//alert('Removed .iframe-container-'+test);
			}
			test_results(test, {'pass': pass, 'message': message.replace('?grav_js_test='+test, '').replace('&grav_js_test='+test, '').replace('&grav_js_remove_admin_bar=1', ''), 'location': location.replace('?grav_js_test='+test, '').replace('&grav_js_test='+test, '').replace('&grav_js_remove_admin_bar=1', '')});
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

	public static function ajax_run_fix_test()
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
				$response = $test_obj->fix();
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

	public function environment()
	{
		return 'local,dev,staging,production';  // you could also use "all"
	}

	public function group()
	{
		return 'WordPress Tests';
	}

	public function label()
	{
		return 'Small Label Here';
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

	public function can_fix() /* OPTIONAL */
	{
		/* Run code here to see if the issue is Fixable */
		if(true)
		{
			return true;
		}

		return false;
	}

	public function fix() /* OPTIONAL */
	{
		/* Run code here to Fix issue */
		if(true)
		{
			return array('pass' => true, 'message' => 'Issue was Fixed.', 'location' => '');
		}

		return array('pass' => false, 'message' => 'Unable to Fix Issue', 'location' => '');
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

	public function environment()
	{
		return 'local,dev,staging,production';  // you could also use "all"
	}

	public function group()
	{
		return 'JS Tests';
	}

	public function label()
	{
		return 'JS Errors';
	}

	public function description()
	{
		return 'Check Basic Pages for JS Errors on Page Load';
	}

	public function js_urls()
	{
		$urls = GRAV_TESTS::get_general_page_urls();
		$js_urls = array();
		foreach ($urls as $url)
		{
			$js_urls[] = array('url' => $url, 'with_admin_bar' => false, 'width' => 860, 'height' => 680);
		}
		return $js_urls;
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



