<?php

class GRAVITATE_TEST_WP_DEBUG
{
	public function type()
	{
		return 'php';
	}

	public function group()
	{
		return 'WordPress Tests';
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
}