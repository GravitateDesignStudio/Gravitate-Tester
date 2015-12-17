<?php

class GRAVITATE_TEST_WP_DEBUG
{
	public function type()
	{
		return 'php';
	}

	public function environment()
	{
		return 'staging,production';
	}

	public function group()
	{
		return 'WordPress Tests';
	}

	public function label()
	{
		return 'WP Debug';
	}

	public function description()
	{
		return 'Make sure WordPress Debug is set to false';
	}

	public function run()
	{
		if(defined('WP_DEBUG') && WP_DEBUG)
		{
			return array('pass' => false, 'message' => 'WordPress Debug is on.  Please turn it off.  This is usually set in the wp-config.php', 'location' => '');
		}
		else
		{
			return array('pass' => true, 'message' => ' WP_DEBUG is set to false', 'location' => '');
		}
	}

	private function get_wp_config_path()
	{
		if(file_exists(ABSPATH . 'wp-config.php'))
		{
			return ABSPATH . 'wp-config.php';
		}

		if(file_exists(ABSPATH . 'wp-config.php'))
		{
			return ABSPATH . '../wp-config.php';
		}

		return false;
	}

	public function can_fix()
	{
		$path = $this->get_wp_config_path();

		if($path && wp_is_writable($path))
		{
			if($contents = file_get_contents($path))
			{
				$contents = GRAVITATE_TESTER::remove_comments($contents);

				if(preg_match('/define[^;]*WP_DEBUG.*(true|TRUE)[^;]*/s', $contents, $matches))
				{
					return true;
				}
			}
		}

		return false;
	}

	public function fix()
	{
		if($path = $this->get_wp_config_path())
		{
			if($contents = file_get_contents($path))
			{
				if(preg_match('/define[^;]*WP_DEBUG.*(true|TRUE)[^;]*/s', $contents, $matches))
				{
					if(!empty($matches[1]))
					{
						$new_string = str_replace($matches[1], 'false', $matches[0]);
						$new_contents = str_replace($matches[0], $new_string, $contents);

						if(file_put_contents($path, $new_contents))
						{
							return array('pass' => true, 'message' => 'Successfully Changed Debug to False.', 'location' => '');
						}
						else
						{
							return array('pass' => false, 'message' => 'Unable to modify wp-config.php', 'location' => '');
						}
					}
				}
			}
		}
	}
}