<?php
/*
Plugin Name: Gravitate Automated Tester
Description: Allows to run Automated Tests in the WP Admin Panel
Version: 1.3.1
Plugin URI: http://www.gravitatedesign.com
Author: Gravitate

*/


if(!empty($_GET['grav_wp_test']) && $_GET['grav_wp_test'] === 'grav-test-php-errors' && !empty($_GET['grav_wp_test_auth']) && $_GET['grav_wp_test_auth'] === md5(AUTH_KEY.'grav-test-php-errors'))
{
	if($grav_tester_options = get_option('gravitate_tester_settings'))
	{
		foreach ($grav_tester_options as $grav_tester_option)
		{
			if(is_array($grav_tester_option))
			{
				if(in_array('grav-test-php-errors', $grav_tester_option))
				{
					ini_set('error_reporting', E_ALL);
					ini_set('display_errors', 1);
				}
			}
		}
	}
}

register_activation_hook( __FILE__, array( 'GRAV_TESTS', 'activate' ));
register_deactivation_hook( __FILE__, array( 'GRAV_TESTS', 'deactivate' ));

add_action('admin_menu', array( 'GRAV_TESTS', 'admin_menu' ));
add_action('init', array( 'GRAV_TESTS', 'init' ));
add_filter('plugin_action_links_'.plugin_basename(__FILE__), array('GRAV_TESTS', 'plugin_settings_link' ));
add_action('wp_ajax_grav_run_test', array( 'GRAV_TESTS', 'ajax_run_test' ));
add_action('wp_ajax_grav_run_fix_test', array( 'GRAV_TESTS', 'ajax_run_fix_test' ));


class GRAV_TESTS {

	private static $version = '1.3.1';
	private static $settings = array();
	private static $page = 'options-general.php?page=gravitate_tester';
	private static $option_key = 'gravitate_tester_settings';
	private static $tests = false;

	public static function init()
	{
		if(is_admin() || !empty($_GET['grav_wp_test']))
		{
			self::setup();
		}

		if(!empty($_GET['grav_wp_test']))
		{
			$test = $_GET['grav_wp_test'];
			$enabled_tests = self::get_enabled_tests();
			if(in_array($test, $enabled_tests))
			{
				$tests = self::get_tests();
				if(!empty($tests[$test]['class']))
				{
					$test_class = $tests[$test]['class'];
					$test_obj = new $test_class();
					$id = sanitize_title($tests[$test]['label']).'-'.dechex(crc32($tests[$test]['file']));

					if(strpos($tests[$test]['file'], '/gravitate-automated-tester/grav_tests/php_errors.php') !== false)
					{
						$id = 'grav-test-php-errors';
					}

					$test_obj->id = $id;

					if(!empty($_GET['grav_wp_test_auth']) && $_GET['grav_wp_test_auth'] === md5(AUTH_KEY.$test))
					{
						if(method_exists($test_obj,'wp_start'))
						{
							$test_obj->wp_start();
						}

						if(method_exists($test_obj,'wp_head'))
						{
							add_action('wp_head', array($test_obj, 'wp_head'), 0);
						}

						if(method_exists($test_obj,'wp_footer'))
						{
							add_action('wp_footer', array($test_obj, 'wp_footer'));
						}

						if(!empty($_GET['grav_wp_remove_admin_bar']))
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
		if(!empty(self::$tests))
		{
			return self::$tests;
		}

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

				if(strpos($file, '/gravitate-automated-tester/grav_tests/php_errors.php') !== false)
				{
					$id = 'grav-test-php-errors';
				}

				$tests[$id] = array('id' => $id, 'type' => $test->type(), 'environment' => 'all', 'group' => $test->group(), 'can_run' => true, 'can_fix' => false, 'js_urls' => "''", 'file' => $file, 'class' => $test_class, 'label' => $test->label(), 'description' => $test->description(), 'fix_confirmation' => '');

				if($test->type() === 'js' && method_exists($test,'js_urls'))
				{
					$tests[$id]['js_urls'] = stripslashes(json_encode($test->js_urls()));
				}

				if(method_exists($test,'environment'))
				{
					$tests[$id]['environment'] = $test->environment();
				}

				if(method_exists($test,'can_run'))
				{
					$tests[$id]['can_run'] = $test->can_run();
				}

				if(method_exists($test,'can_fix') && method_exists($test,'fix'))
				{
					if($test->can_fix())
					{
						$tests[$id]['can_fix'] = true;

						if(method_exists($test,'fix_confirmation'))
						{
							$tests[$id]['fix_confirmation'] = $test->fix_confirmation();
						}

					}
				}
			}
		}

		ksort($tests);

		self::$tests = $tests;

		return self::$tests;
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


	public static function is_editable()
	{
		if(self::guess_environment() === 'local')
		{
			return true;
		}

		if(!defined('DISALLOW_FILE_EDIT') || (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT == false))
		{
			return true;
		}

		return false;
	}


	public static function remove_comments($contents='')
	{
		$contents = preg_replace('!/\*.*?\*/!s', '', $contents);
		$contents = preg_replace('/\n\s*\n/', "\n", $contents);
		$contents = preg_replace('![ \t]*//.*[ \t]*[\r\n]!', '', $contents);

		return $contents;
	}


	public static function url_add_auth($url='', $id='')
	{
		if($url)
		{
			$url.= (strpos($url, '?') === false ? '?' : '&');
		}
		$url.= 'grav_wp_test='.$id.'&grav_wp_test_auth='.md5(AUTH_KEY.$id);
		return $url;
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

		$urls = array_merge($urls, self::get_template_page_urls());

		return array_unique($urls);
	}


	public static function get_template_page_urls()
	{
		$site_url = site_url('/');
		$urls = array($site_url,$site_url.'?s=grav-test',$site_url.'404-grav-test-url');

		$urls = array_merge($urls, self::get_post_type_page_urls());

		return array_unique($urls);
	}

	public static function get_post_type_page_urls($custom_post_types=true)
	{
		$post_types = get_post_types(array('public' => true, '_builtin' => false));

		$post_types[] = 'post';
		$post_types[] = 'page';

		foreach ($post_types as $post_type)
		{
			foreach(get_posts(array('post_type' => $post_type, 'posts_per_page' => 1)) as $post)
			{
				$urls[] = get_permalink($post->ID);
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

		$fields = array();

		switch ($location)
		{

			case 'advanced':
				$fields['search_options'] = array('type' => 'checkbox', 'label' => 'Search Settings', 'options' => $search_options, 'description' => '');

			break;

			default:
			case 'general':


				$environments = self::get_environments();
				$fields['environment'] = array('type' => 'select', 'label' => 'Environment', 'options' => $environments, 'description' => '');



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
		// Make sure that this Plugin Loads before the others.
		if($active_plugins = get_option('active_plugins'))
		{
			$plugin_path = plugin_basename( __FILE__ );
			if(!empty($active_plugins[0]) && $active_plugins[0] !== $plugin_path)
			{
				$plugin_count = count($active_plugins);
				$out = array_splice($active_plugins, array_search($plugin_path, $active_plugins), 1);
				array_splice($active_plugins, 0, 0, $out);

				if(count($active_plugins) === $plugin_count && $active_plugins[0] === $plugin_path)
				{
					update_option('active_plugins', $active_plugins);
				}
			}
		}

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
			<a href="<?php echo self::$page;?>&section=run_tests" class="<?php echo self::get_current_tab($_GET['section'], 'run_tests'); ?>">Run Tests</a> &nbsp; | &nbsp;
			<a href="<?php echo self::$page;?>&section=settings" class="<?php echo self::get_current_tab($_GET['section'], 'settings'); ?>">Settings</a> &nbsp; | &nbsp;
			<a href="<?php echo self::$page;?>&section=developers" class="<?php echo self::get_current_tab($_GET['section'], 'developers'); ?>">Developers</a>
		</div>


		<br style="clear:both;">
		<br>

		<?php

		$section = (!empty($_GET['section']) ? $_GET['section'] : 'run_tests');

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

    private static function get_environments()
    {
    	self::get_settings();

		$tests = self::get_tests();
		$enabled_tests = self::get_enabled_tests();

		$environments = array('all', 'local', 'dev', 'staging', 'production');

		$environment_default = self::guess_environment();

		foreach ($tests as $test)
		{
			$environments = array_merge($environments, explode(',', $test['environment']));
		}

		$environments = array_unique($environments);

		unset($environments[array_search('all', $environments)]);

		return $environments;
    }

	private static function run_tests()
	{
		self::get_settings();

		$tests = self::get_tests();
		$enabled_tests = self::get_enabled_tests();

		$environments = self::get_environments();

		?>

		<style>
		.passed {
			color: #0A0;
		}
		.failed {
			color: #D00;
		}
		.testing {
			color: #999;
		}
		#the-list .errors_list input {
			display: block;
			border: 1px dashed #c8c8c8;
			padding: 0 6px;
			background-color: #fcfcfc;
			margin: 3px 0 0;
			font-size: 0.7rem;
			cursor: text;
			width: 100%;
		}
		#the-list td .fix-button {
			display: none;
		}
		#the-list th.info {
			width:20%;
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
		#the-list td.description {
			width:40%;
		}
		#the-list td.status {
			width:40%;
			max-width: 200px;
			padding: 0;
		}
		#the-list td.status h4 {
			/*padding-right: 8px;*/
			height: 14px;
			margin: 2px 0px 0px;
			font-size: 13px;
			display: inline-block;
		}
		#the-list td a.show_errors, #the-list td a.hide_errors {
			display: none;
			margin-left: 8px;
			color: #D7825C;
			font-weight: normal;
		}
		#the-list td a.show_errors:hover, #the-list td a.hide_errors:hover {
			text-decoration: underline;
		}
		#the-list td.status span {
			font-size: 11px;
			display: block;
			line-height: 12px;
		}
		#the-list .errors_list {
			height: 0;
			opacity: 0;
			transition: all .2s;
			overflow-y: auto;
			padding: 0 10px;
			float: right;
			width: 80%;
			max-height: 300px;
			margin-right: 20px
		}
		#the-list .errors_list h4 {
			margin: 18px 0 0;
		}
		#the-list .test-errors th {
			padding: 0;
			background-color: #fff0f0;
			border-color: #FF8394
		}
		#the-list .open-errors .errors_list {
			height: auto;
			opacity: 1;
			margin-bottom: 20px;
		}
		#the-list .open-errors.test-data td, #the-list .open-errors.test-data th {
			box-shadow: none;
		}
		#the-list td.actions {
			width:10%;
			white-space:nowrap;
			text-align:right;
		}
		#the-list td.actions .button {
			height: 20px;
			line-height: 18px;
		}

		.environment-label h3 {
			/*margin-left: 20%;
			display: inline-block;*/
			margin-bottom: 6px;
		}
		.environment-label label {
			/*margin-left: 20%;
			display: inline-block;*/
			margin-bottom: 6px;
		}

		.cssload-container {
			width: 20px;
			height: 10px;
			text-align: center;
			/*display: none;*/
			display: inline-block;
		}

		.cssload-double-torus {
			width: 8px;
			height: 8px;
			margin: 0 auto;
			border: 2px solid;
			border-radius: 50%;
			border-color: rgba(0,0,0,0.7) rgba(0, 0, 0, 0.2) rgba(0,0,0,0.2);
			animation: cssload-spin 1320ms infinite linear;
				-o-animation: cssload-spin 1320ms infinite linear;
				-ms-animation: cssload-spin 1320ms infinite linear;
				-webkit-animation: cssload-spin 1320ms infinite linear;
				-moz-animation: cssload-spin 1320ms infinite linear;
		}



		@keyframes cssload-spin {
			100%{ transform: rotate(360deg); transform: rotate(360deg); }
		}

		@-o-keyframes cssload-spin {
			100%{ -o-transform: rotate(360deg); transform: rotate(360deg); }
		}

		@-ms-keyframes cssload-spin {
			100%{ -ms-transform: rotate(360deg); transform: rotate(360deg); }
		}

		@-webkit-keyframes cssload-spin {
			100%{ -webkit-transform: rotate(360deg); transform: rotate(360deg); }
		}

		@-moz-keyframes cssload-spin {
			100%{ -moz-transform: rotate(360deg); transform: rotate(360deg); }
		}
		</style>


		<div class="environment-label" style="float:left; text-align:left; width:50%;">
			<h3>Environment - <?php echo self::$settings['environment'];?></h3>
			<label><input type="checkbox" id="show-all-tests"><small>Show All Tests</small></label>
		</div>
		<div style="float: right; text-align:right; width:50%;">
		<br>
			<button onclick="run_all_tests();" class="button button-primary">Run All Tests</a>
		</div>
		<br class="clear:both;">
		<br>&nbsp;
		<br>

		<table class="wp-list-table widefat plugins" cellspacing="0">
			<thead>
				<tr>
					<th class="manage-column column-cb" id="cb" scope="col">
						Test
					</th>
					<th style="" class="manage-column column-description" scope="col">
						Description
					</th>
					<th style="" class="manage-column column-description" scope="col">
						Status
					</th>
					<th style="" class="manage-column column-description" scope="col">

					</th>
				</tr>
			</thead>

			<tbody id="the-list">
				<?php foreach ($enabled_tests as $num => $test) { ?>
					<?php if(!empty($tests[$test])) { ?>
					<tr class="event active test-data test-<?php echo $tests[$test]['id']; ?><?php if($tests[$test]['can_run']){?> environment-<?php echo implode(' environment-', explode(',', $tests[$test]['environment']));?><?php } ?> ">
						<th class="info check-column">
							<h4 style="margin:0;"><?php echo $tests[$test]['label']; ?></h4>
						</td>
						<td class="description">
							<?php echo $tests[$test]['description']; ?>
						</td>
						<td class="status">
							<h4><?php if(!$tests[$test]['can_run']){?><span class="testing">Your site does not match the criteria to be able to run this test.</span><?php } ?></h4>
							<div class="cssload-container">
								<div class="cssload-double-torus"></div>
							</div>
							<a class="show_errors" href="#">Show Details</a><a class="hide_errors" href="#">Hide Details</a>
							<span></span>
						</td>
						<td class="actions">
						<?php if($tests[$test]['can_run']){?>
							<?php if($tests[$test]['can_fix']){ ?>
								<button class="button fix-button" onclick="<?php if($tests[$test]['fix_confirmation']){ ?>if(confirm('<?php echo $tests[$test]['fix_confirmation'];?>')){<?php } ?>run_ajax_fix('<?php echo $tests[$test]['id']; ?>');<?php if($tests[$test]['fix_confirmation']){ ?>}<?php } ?>">Fix</button>
							<?php } ?>

							<button class="button" onclick="run_ajax_test('<?php echo $tests[$test]['id']; ?>', 500);">Run Test</button>
						<?php } ?>
						</td>
					</tr>
					<tr class="event active test-errors test-<?php echo $tests[$test]['id']; ?>_error_list">
						<th colspan="4" class="check-column">
							<div class="errors_list"></div>
						</th>
					</tr>
					<?php } ?>
				<?php } ?>
			</tbody>
		</table>

		<script>

		jQuery('#the-list td input, .cssload-container').hide();

		function update_environments(val)
		{
			if(val === 'all')
			{
				jQuery('#the-list tr.test-data').css('opacity', 1).removeClass('inactive');
			}
			else
			{
				jQuery('#the-list tr.test-data').addClass('inactive');
				jQuery('#the-list tr.environment-all, #the-list tr.environment-'+val).removeClass('inactive');
			}
		}

		jQuery('.status .show_errors').on('click', function(e){
			e.preventDefault();
			jQuery(this).hide();
			jQuery(this).closest('.test-data').addClass('open-errors');
			jQuery(this).closest('.test-data').next().addClass('open-errors');
			jQuery(this).parent().find('.hide_errors').show().css('display','inline');
		});

		jQuery('.status .hide_errors').on('click', function(e){
			e.preventDefault();
			jQuery(this).hide();
			jQuery(this).closest('.test-data').removeClass('open-errors');
			jQuery(this).closest('.test-data').next().removeClass('open-errors');
			jQuery(this).parent().find('.show_errors').show().css('display','inline');
		});

		update_environments('<?php echo self::$settings['environment'];?>');

		jQuery('#the-list tr.inactive').hide();

		jQuery('#show-all-tests').on('click', function(e){
			if(jQuery(this).is(':checked'))
			{
				jQuery('#the-list tr.inactive').show();
			}
			else
			{
				jQuery('#the-list tr.inactive').hide();
			}
		});

		var grav_tests = [];
		<?php foreach ($enabled_tests as $num => $test) { ?>
			<?php if(!empty($tests[$test])) { ?>
			grav_tests['<?php echo $tests[$test]['id'];?>'] = {'id': '<?php echo $tests[$test]['id'];?>', 'type': '<?php echo $tests[$test]['type'];?>', 'can_run': '<?php echo $tests[$test]['can_run'];?>', 'environment': '<?php echo $tests[$test]['environment'];?>', 'js_urls': <?php echo $tests[$test]['js_urls'];?>, 'url_auth': '<?php echo self::url_add_auth('', $test);?>'};
			<?php } ?>
		<?php } ?>

		var grav_js_tests_failed = [];
		<?php foreach ($enabled_tests as $num => $test) { ?>
			<?php if(!empty($tests[$test]['type']) && $tests[$test]['type'] === 'js') { ?>
			grav_js_tests_failed['<?php echo $tests[$test]['id'];?>'] = false;
			<?php } ?>
		<?php } ?>

		var _grav_tests_run_all_tests_count = 0;

		function run_all_tests()
		{
			if(_grav_tests_run_all_tests_count > 0)
			{
				if(!confirm('It is best to not Run all the Tests multiple times. Instead only the run the ones that have Errors individually after you have worked on fixing them.'))
				{
					return false;
				}
			}

			var environment = '<?php echo self::$settings['environment'];?>';
			var num = 1;
			for(var t in grav_tests)
			{
				if(grav_tests[t]['can_run'] && (grav_tests[t]['environment'] === 'all' || grav_tests[t]['environment'].indexOf(environment) > -1))
				{
					run_ajax_test(grav_tests[t]['id'], (500*num));
				}
				num++;
			}

			_grav_tests_run_all_tests_count++;
		}

		function run_ajax_fix(test)
		{
			var environment = '<?php echo self::$settings['environment'];?>';

			jQuery('.test-' + test + ' .status > h4').removeClass('failed').removeClass('passed').addClass('testing').html('Fixing');
			jQuery('.test-' + test + ' .status .cssload-container').show();
			jQuery('.test-' + test + ' .status span').html('');
			jQuery('.test-' + test + ' .status .show_errors, .test-' + test + ' .status .hide_errors').hide();
			jQuery('.test-' + test).removeClass('open-errors');
			jQuery('.test-' + test + '_error_list').removeClass('open-errors');
			jQuery('.test-' + test + '_error_list .errors_list').html('');
			jQuery('.test-' + test + ' .actions .fix-button').hide();

			jQuery.post('<?php echo admin_url("admin-ajax.php");?>',
			{
				'action': 'grav_run_fix_test',
				'grav_test': test,
				'grav_test_environment': environment
			},
			function(response)
			{
				test_results(response, test);

			}).fail(function()
			{
				jQuery('.test-' + test + ' .status > h4').removeClass('passed').removeClass('failed').removeClass('testing').html('Unknown');
				jQuery('.test-' + test + ' .status span').html('Error Getting Response from Fix.');
			});
		}

		function run_ajax_test(test, msec)
		{
			if(grav_tests[test] !== 'undefined')
			{
				var environment = '<?php echo self::$settings['environment'];?>';

				grav_js_tests_failed[test] = false;

				jQuery('.test-' + test + ' .status > h4').removeClass('failed').removeClass('passed').addClass('testing').html('Testing');
				jQuery('.test-' + test + ' .status .cssload-container').show();
				jQuery('.test-' + test + ' .status span').html('');
				jQuery('.test-' + test + ' .status .show_errors, .test-' + test + ' .status .hide_errors').hide();
				jQuery('.test-' + test).removeClass('open-errors');
				jQuery('.test-' + test + '_error_list').removeClass('open-errors');
				jQuery('.test-' + test + '_error_list .errors_list').html('');
				jQuery('.test-' + test + ' .actions .fix-button').hide();

				setTimeout(function(){

					if(grav_tests[test]['type'] === 'php')
					{
						jQuery.post('<?php echo admin_url("admin-ajax.php");?>',
						{
							'action': 'grav_run_test',
							'grav_test': test,
							'grav_test_environment': environment
						},
						function(response)
						{
							test_results(response, test);

						}).fail(function()
						{
							jQuery('.test-' + test + ' .status .cssload-container').hide();
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

		  						url['auth'] = grav_tests[test]['url_auth'];

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
			var auth = (url['auth'] !== 'undefined' ? url['auth'] : '');

			src+= (src.indexOf('?') > -1 ? '&' : '?')+auth;

			var width = (url['width'] !== 'undefined' ? url['width'] : '800');
			var height = (url['height'] !== 'undefined' ? url['height'] : '600');
			var admin_bar = (url['with_admin_bar'] !== 'undefined' ? url['with_admin_bar'] : false);

			if(src)
			{
				setTimeout(function(){
					if(!grav_js_tests_failed[test])
					{
						jQuery('<div class="iframe-container-'+test+'" id="'+url_id+'" style="width:0;height:0;overflow:hidden;"><iframe width="'+width+'" height="'+height+'" name="'+url_id+'" src="'+src+(!admin_bar ? '&grav_wp_remove_admin_bar=1' : '')+'"></div>').appendTo('body').css('visibility', 'hidden');
					}
				}, 500*sec);
			}
		}

		function test_remove_queries(value, test)
		{
			return value.replace('?'+grav_tests[test]['url_auth'], '').replace('&'+grav_tests[test]['url_auth'], '').replace(grav_tests[test]['url_auth'], '').replace('&grav_wp_remove_admin_bar=1', '');
		}

		function test_results(response, test)
		{
			var data = false;

			if(typeof test === 'undefined')
			{
				test = '';
			}
			else
			{
				response['test'] = test;
			}

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
					var test = (typeof data['test'] !== 'undefined' ? data['test'] : test);
					var pass = (typeof data['pass'] !== 'undefined' ? data['pass'] : '');
					var message = (typeof data['message'] !== 'undefined' ? data['message'] : '');
					var errors = (typeof data['errors'] !== 'undefined' ? data['errors'] : []);

					message = test_remove_queries(message, test);

					jQuery('.test-' + test + ' .status .cssload-container').hide();

					if(pass === true)
					{
						jQuery('.test-' + test + ' .status h4').addClass('passed').removeClass('failed').removeClass('testing').html('Passed');
						jQuery('.test-' + test + ' .actions .fix-button').css('display', 'none');
					}
					else if(pass === false)
					{
						jQuery('.test-' + test + ' .status h4').addClass('failed').removeClass('passed').removeClass('testing').html('Failed');
						jQuery('.test-' + test + ' .actions .fix-button').css('display', 'inline-block');
					}
					else
					{
						jQuery('.test-' + test + ' .status h4').removeClass('failed').removeClass('passed').removeClass('testing').html('Unknown');
						jQuery('.test-' + test + ' .actions .fix-button').css('display', 'none');
					}

					if(message)
					{
						jQuery('.test-' + test + ' .status span').html(message);
					}

					if(errors.length > 0)
					{
						jQuery('.test-' + test + ' .status .show_errors').show().css('display','inline');

						jQuery('.test-' + test + '_error_list .errors_list').html('');

						var error_message, error_location;

						for(var e in errors)
						{
							error_message = (typeof errors[e]['message'] !== 'undefined' ? errors[e]['message'] : '');
							error_location = (typeof errors[e]['location'] !== 'undefined' ? errors[e]['location'] : '');
							error_line = (typeof errors[e]['line'] !== 'undefined' ? ' (Line: '+errors[e]['line']+')' : '');
							error_details = (typeof errors[e]['details'] !== 'undefined' ? errors[e]['details'] : '');

							error_message = test_remove_queries(error_message, test);
							error_location = test_remove_queries(error_location, test)+error_line;

							jQuery('<h4>'+error_message+'</h4>'+(error_location ? '<input type="text" value="'+error_location+'" readonly="readonly">' : '')+(error_details ? '<small>'+error_details+'</small>' : '')).appendTo('.test-' + test + '_error_list .errors_list');
						}
					}
				}
				else
				{
					jQuery('.test-' + test + ' .status h4').removeClass('passed').removeClass('failed').removeClass('testing').html('Unknown');
					jQuery('.test-' + test + ' .status span').html('Error Getting Response from Test.');
				}
			}
		}

		function grav_tests_js_pass(response)
		{
			if(response['test'] !== 'undefined')
			{
				/* If already Failed Test then ignore all other responses */
				if(grav_js_tests_failed[response['test']] === true)
				{
					return;
				}

				if(!response['pass'] && grav_js_tests_failed[response['test']] === false)
				{
					grav_js_tests_failed[response['test']] = true;
					jQuery('.iframe-container-'+response['test']).remove();
				}
				test_results(response);
			}
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
				$id = sanitize_title($test['label']).'-'.dechex(crc32($test['file']));

				if(strpos($test['file'], '/gravitate-automated-tester/grav_tests/php_errors.php') !== false)
				{
					$id = 'grav-test-php-errors';
				}

				$test_obj->id = $id;

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
		sleep(1);
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

	public function can_run() /* OPTIONAL */
	{
		return true;
	}

	public function run()
	{
		if(true)
		{
			return array('pass' => true, 'message' => 'Your Test Passed');
		}
		else if(false)
		{
			$errors = array();
			$errors[] = array('message' => 'It Failed because of this', , 'location' => 'somefile.php', 'line' => 43, 'details' => 'Longer Details Here.');
			return array('pass' => false, 'message' => 'Your Test Failed', 'errors' => $errors);
		}
		else
		{
			return array('pass' => null, 'message' => 'Unknown Error');
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

	public function fix_confirmation() /* OPTIONAL */
	{
		return 'Make sure to check the site after you fix this issue.';
	}

	public function fix() /* OPTIONAL */
	{
		/* Run code here to Fix issue */

		/* Check to see if it still has errors */
		return $this->run();
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
		return 'local,dev,staging,production';  /* you could also use "all" */
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

	public function wp_head()
	{
		?&gt;
		&lt;script type="text/javascript"&gt;

			var grav_test_errors = [];

			window.onerror = function(error, file, linenumber)
			{
				var error = {
					'message': 'JS Error loading ('+window.location.href+') '+ error,
					'location': file,
					'line': linenumber
				};

				grav_test_errors.push(error);
			};

		&lt;/script&gt;
		&lt;?php
	}

	public function wp_footer()
	{
		?&gt;
		&lt;script type="text/javascript"&gt;


		jQuery(window).on('load', function()
		{
			if(grav_test_errors.length > 0)
			{
				var response = {
					'test': '&lt;?php echo $this->id;?&gt;',
					'pass': false,
					'errors': grav_test_errors,
					'message': 'Detected ('+grav_test_errors.length+') JS Errors'
				};
	  		}
	  		else
	  		{
	  			var response = {
					'test': '&lt;?php echo $this->id;?&gt;',
					'pass': true,
					'message': 'No JS Errors Detected'
				};
	  		}
	  		parent.grav_tests_js_pass(response);
		});


		&lt;/script&gt;
		&lt;?php
	}
}

				</textarea>
				</blockquote>
				<br>
				<h2>Injector Methods</h2>

				<h3>wp_start(), wp_head(), wp_footer()</h3>
				These methods will allow you to run php code as the test is being loaded from another script.
				<br>
				Ex.<br>
					wp_remote_get(site_url());<br>
<br>
				In order to use the Injector Methods you must authenticate the url by passing it through the url_add_auth() method.<br>
				Ex.<br>
				    wp_remote_get(GRAV_TESTS::url_add_auth(site_url(), $this->id));<br>
				    <br>
				This is automatically done for you when using the js_urls() method as seen above.  So you only need to do this when manually fetching your own urls.<br>
				It is recommended to use wp_remote_get() over file_get_contents() as it handles redirects and errors better.
		</div>
		<?php

	}

}



