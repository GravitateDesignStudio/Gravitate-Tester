<?php

class GRAVITATE_TEST_HTML_VALID
{
	public function group()
	{
		return 'HTML Tests';
	}

	public function description()
	{
		return 'Check that your Pages are HTML Valid (W3C)';
	}

	private function get_contents($url)
	{
		$ch=curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
		$result=curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	public function run()
	{
		if(gethostbyname($_SERVER['HTTP_HOST']) === '127.0.0.1')
		{
			return array('pass' => null, 'message' => 'Cannot Validate when using Localhost.', 'location' => $item->url);
		}

		$loaded_pages = 0;

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
								if(strpos($item->url, site_url()) !== false)
								{
									if($contents = $this->get_contents('https://validator.w3.org/nu/?doc='.$item->url))
									{
										if(strpos($contents, '<div id="results">') === false)
										{
											return array('pass' => null, 'message' => 'Error loading W3C Validator', 'location' => '');
										}

										if(strpos($contents, '<li class="error">') !== false)
										{
											return array('pass' => false, 'message' => 'Page ('.$item->url.') is not HTML Valid.  <a target="_blank" href="'.'https://validator.w3.org/nu/?doc='.$item->url.'">Learn More</a>', 'location' => $item->url);
										}

										$loaded_pages++;
									}
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
							if($contents = $this->get_contents('https://validator.w3.org/nu/?doc='.$url))
							{
								if(strpos($contents, '<div id="results">') === false)
								{
									return array('pass' => null, 'message' => 'Unknown Error loading W3C Validator', 'location' => '');
								}

								if(strpos($contents, '<li class="error">') !== false)
								{
									return array('pass' => false, 'message' => 'Page ('.$url.') is not HTML Valid.  <a target="_blank" href="'.'https://validator.w3.org/nu/?doc='.$url.'">Learn More</a>', 'location' => $url);
								}

								$loaded_pages++;
							}
						}
					}
				}
			}
		}


		if(!$loaded_pages)
		{
			return array('pass' => null, 'message' => 'Error loading W3C Validator', 'location' => '');
		}

		return array('pass' => true, 'message' => 'Successfully Validated ('.$loaded_pages.') Pages', 'location' => '');
	}
}