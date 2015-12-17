<?php

class GRAV_TEST_WP_PLUGINS_VERSIONS
{
	public function type()
	{
		return 'php';
	}

	public function environment()
	{
		return 'all';
	}

	public function group()
	{
		return 'WordPress Tests';
	}

	public function label()
	{
		return 'Plugins Updated';
	}

	public function description()
	{
		return 'Make sure WordPress Plugins are the Latest Stable Version';
	}

	private function get_plugin_latest_version($plugin='')
	{
		if(!function_exists('plugins_api'))
		{
		    require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
		}

		$args = array(
		    'slug' => $plugin,
		    'fields' => array(
		        'version' => true,
		    )
		);

		/** Prepare our query */
		$call_api = plugins_api( 'plugin_information', $args );

		/** Check for Errors & Display the results */
		if(is_wp_error($call_api))
		{
		    /* $api_error = $call_api->get_error_message(); */
		    return false;
		}
		else
		{
		    if(!empty($call_api->version))
		    {
		        return $call_api->version;
		    }
		    return false;
		}
		return false;
	}

	public function run()
	{
		$active = get_option('active_plugins');
		$updates = get_option('_site_transient_update_plugins');

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if(!empty($all_plugins))
		{
			foreach($all_plugins as $plugin_slug => $plugin)
			{
				$version = $this->get_plugin_latest_version(dirname($plugin_slug));

				if(!empty($version) && !empty($plugin['Version']) && $plugin['Version'] < $version)
				{
					return array('pass' => false, 'message' => 'Plugin is not Up to Date ('.$plugin['Name'].') '.$plugin['Version'].' < '.$version, 'location' => $plugin_slug);
				}
			}
			return array('pass' => true, 'message' => 'All WordPress Plugins are Up to Date', 'location' => '');
		}
		return array('pass' => null, 'message' => 'Unable to check Plugin Updates', 'location' => '');

	}
}