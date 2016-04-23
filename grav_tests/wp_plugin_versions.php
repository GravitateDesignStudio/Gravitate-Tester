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
		if(!function_exists('plugins_api') && file_exists(ABSPATH . 'wp-admin/includes/plugin-install.php'))
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

	public function get_old_plugins()
	{
		$old_plugins = array();

		wp_update_plugins();

		$active = get_option('active_plugins');

		if(!function_exists('get_plugins') && file_exists(ABSPATH . 'wp-admin/includes/plugin.php'))
		{
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if(!function_exists('get_plugins'))
		{
			return null;
		}

		$all_plugins = get_plugins();

		if(empty($all_plugins))
		{
			return null;
		}

		foreach($all_plugins as $plugin_slug => $plugin)
		{
			if(in_array($plugin_slug, $active))
			{
				$version = $this->get_plugin_latest_version(dirname($plugin_slug));

				if(!empty($version) && !empty($plugin['Version']) && $plugin['Version'] < $version)
				{
					$old_plugins[] = array('name' => $plugin['Name'], 'path' => $plugin_slug, 'current_version' => $plugin['Version'], 'new_version' => $version);
				}
			}
		}

		return $old_plugins;
	}

	public function run()
	{
		$old_plugins = $this->get_old_plugins();

		$errors = array();

		if($old_plugins === null)
		{
			return array('pass' => null, 'message' => 'Unable to check Plugin Updates', 'location' => '');
		}

		if(!empty($old_plugins))
		{
			foreach ($old_plugins as $plugin)
			{
				$errors[] = array('message' => 'Plugin is not Up to Date ('.$plugin['name'].') '.$plugin['current_version'].' < '.$plugin['new_version'], 'location' => $plugin['path']);
			}
		}

		if($errors)
		{
			return array('pass' => false, 'message' => 'There are ('.count($errors).') Plugins that out of Date', 'errors' => $errors);
		}

		return array('pass' => true, 'message' => 'All WordPress Plugins are Up to Date');
	}

	public function can_fix()
	{
		if(GRAV_TESTS::is_editable())
		{
			$plugin_file = ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			if(file_exists($plugin_file) && function_exists('activate_plugin'))
			{
				require_once($plugin_file);
				if(class_exists('Plugin_Upgrader') && method_exists('Plugin_Upgrader', 'upgrade'))
				{
					return true;
				}
			}
		}

		return false;
	}

	public function fix()
	{
		if($this->can_fix())
		{
			$old_plugins = $this->get_old_plugins();

			if($old_plugins === null)
			{
				return array('pass' => null, 'message' => 'Unable to check Plugin Updates');
			}

			if(!empty($old_plugins))
			{
				$upgrader = new Plugin_Upgrader();
				foreach ($old_plugins as $plugin)
				{
					$upgrader->upgrade($plugin['path']);
					activate_plugin($plugin['path']);

					wp_update_plugins();

					sleep(1);
				}
			}
		}

		return $this->run();
	}
}