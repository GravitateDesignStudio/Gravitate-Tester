<?php

class GRAV_TEST_PHP_ERRORS
{
	public function type()
	{
		return 'php';
	}

	public function environment()
	{
		return 'dev,staging,production';
	}

	public function group()
	{
		return 'PHP Tests';
	}

	public function label()
	{
		return 'PHP Errors';
	}

	public function description()
	{
		return 'Checks pages for PHP Errors, Notices and Warnings';
	}

	public function wp_start()
	{
		ini_set('error_reporting', E_ALL);
		ini_set('display_errors', 1);

		if(function_exists('register_shutdown_function'))
		{
			register_shutdown_function(array(__CLASS__, 'grav_test_php_errors_shutdown_function'));
		}
	}

	public function grav_test_php_errors_shutdown_function()
	{
	    $error = error_get_last();
	    if($error['type'] === E_ERROR)
	    {
	        echo '<b>PHP Fatal Error</b>: '.$error['message'].' in <b>'.$error['file'].'</b> on line <b>'.$error['line'].'</b>';
	    }
	}

	public function run()
	{
		$loaded_pages = 0;

		$urls = GRAV_TESTS::get_general_page_urls();

		$unique_errors = array();
		$errors = array();

		foreach ($urls as $url)
		{
			if($contents = wp_remote_get(GRAV_TESTS::url_add_auth($url, $this->id), array('sslverify' => false, 'timeout' => 15)))
			{
				if(!empty($contents['body']))
				{
					preg_match_all('/(.+):(.+) in (\/.+) on line ([0-9]+)/', $contents['body'], $matches);

					if(!empty($matches[1]) && !empty($matches[2]) && !empty($matches[3]) && !empty($matches[4]))
					{
						foreach ($matches[1] as $key => $error)
						{
							$unique_errors[sanitize_title($matches[0][$key])] = array(
								'message' => 'PHP '.$matches[1][$key].' - '.$matches[2][$key],
								'location' => $matches[3][$key],
								'line' => $matches[4][$key]
							);
						}
					}
				}

				if(strpos($contents['body'], '<html') !== false)
				{
					$loaded_pages++;
				}
			}
		}

		if(!empty($unique_errors))
		{
			foreach($unique_errors as $key => $error)
			{
				$errors[] = $error;
			}
		}

		if(!empty($errors))
		{
			return array(
				'pass' => false,
				'errors' => $errors,
				'message' => 'There are ('.count($errors).') PHP Errors'
			);
		}

		if(!$loaded_pages)
		{
			return array(
				'pass' => null,
				'message' => 'Error loading Pages'
			);
		}

		return array(
			'pass' => true,
			'message' => 'No Errors found in ('.$loaded_pages.') Pages'
		);
	}
}