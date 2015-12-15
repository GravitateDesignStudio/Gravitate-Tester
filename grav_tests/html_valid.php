<?php

class GRAVITATE_TEST_HTML_VALID
{
	public function type()
	{
		return 'php';
	}

	public function group()
	{
		return 'HTML Tests';
	}

	public function description()
	{
		return 'Check that your Pages are HTML Valid (W3C)';
	}

	public function run()
	{
		if(gethostbyname($_SERVER['HTTP_HOST']) === '127.0.0.1')
		{
			return array('pass' => null, 'message' => 'Cannot Validate when using Localhost.', 'location' => $item->url);
		}

		$loaded_pages = 0;

		$urls = GRAVITATE_TESTER::get_general_page_urls();

		foreach ($urls as $url)
		{
			if($contents = wp_remote_get('https://validator.w3.org/nu/?doc='.$url))
			{
				if(strpos($contents, '<div id="results">') === false)
				{
					return array('pass' => null, 'message' => 'Error loading W3C Validator', 'location' => '');
				}

				if(strpos($contents, '<li class="error">') !== false)
				{
					return array('pass' => false, 'message' => 'Page ('.$url.') is not HTML Valid.  <a target="_blank" href="'.'https://validator.w3.org/nu/?doc='.$url.'">Learn More</a>', 'location' => $url);
				}

				$loaded_pages++;
			}
		}

		if(!$loaded_pages)
		{
			return array('pass' => null, 'message' => 'Error loading W3C Validator', 'location' => '');
		}

		return array('pass' => true, 'message' => 'Successfully Validated ('.$loaded_pages.') Pages', 'location' => '');
	}
}