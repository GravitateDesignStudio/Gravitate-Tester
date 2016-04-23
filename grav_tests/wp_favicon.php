<?php

class GRAV_TEST_WP_FAVICON
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
		return 'WP Favicon';
	}

	public function description()
	{
		return 'Make sure Favicon exists';
	}

	public function run()
	{
		if($contents = wp_remote_get(site_url(), array('sslverify' => false, 'timeout' => 15)))
		{
			if(!empty($contents['body']))
			{
				$favicon = '';

				preg_match('/< *link[^>]*(rel.*icon.*href *= *["\']?(([^"\']*)\.ico)|href *= *["\']?(([^"\']*)\.ico).*rel.*icon).*>/i', $contents['body'], $matches);
				if(!empty($matches[2]))
				{
					$favicon = $matches[2];
				}
				else if(!empty($matches[4]))
				{
					$favicon = $matches[4];
				}


				if(file_get_contents($favicon))
				{
					return array('pass' => true, 'message' => 'WordPress found Favicon');
				}

				return array('pass' => false, 'message' => 'WordPress did not find Favicon');
			}
		}

		return array('pass' => null, 'message' => 'Could not load Template.');
	}
}