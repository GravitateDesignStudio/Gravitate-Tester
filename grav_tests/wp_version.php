<?php

class GRAV_TEST_WP_VERSION
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
		return 'WP Updated';
	}

	public function description()
	{
		return 'Make sure WordPress is Latest Stable Version';
	}

	public function run()
	{
		if($contents = file_get_contents('http://api.wordpress.org/core/version-check/1.0/?version='.get_bloginfo('version')))
		{
			if(strpos($contents, 'latest') !== false)
			{
				return array('pass' => true, 'message' => 'Your WordPress Core is up to date', 'location' => '');
			}
			else if(strpos($contents, 'upgrade') !== false)
			{
				return array('pass' => false, 'message' => 'Your WordPress Core is out of date.', 'location' => '');
			}
		}
		return array('pass' => null, 'message' => 'Error checking WP API', 'location' => '');
	}
}