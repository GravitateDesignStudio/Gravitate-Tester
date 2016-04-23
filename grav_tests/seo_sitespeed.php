<?php

class GRAV_TEST_SEO_SITESPEED
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
		return 'SEO Tests';
	}

	public function label()
	{
		return 'SEO Sitespeed';
	}

	public function description()
	{
		return 'Check Google Insights API Speed Rating. Should be 75 or above.';
	}

	public function run()
	{
		if(GRAV_TESTS::guess_environment() === 'local')
		{
			return array(
				'pass' => null,
				'message' => 'Cannot Validate when using Localhost. Try on a valid Hostname.',
				'location' => $item->url
			);
		}

		$urls = GRAV_TESTS::get_template_page_urls();

		$errors = array();

		$loaded_pages = 0;

		$total_score = 0;

		foreach ($urls as $url)
		{
			if($response = wp_remote_get('https://www.googleapis.com/pagespeedonline/v2/runPagespeed?url='.$url, array('sslverify' => false, 'timeout' => 30)))
			{
				if(!empty($response['body']))
				{
					$json = json_decode($response['body'], true);

					if(!empty($json) && is_array($json) && !empty($json['ruleGroups']['SPEED']['score']))
					{
						$score = $json['ruleGroups']['SPEED']['score'];

						if($score > 0)
						{
							$total_score+= $score;

							if($score < 75)
							{
								$errors[] = array(
									'message' => 'Page Speed rating is ('.$score.'). <a target="_blank" href="https://developers.google.com/speed/pagespeed/insights/?url='.$url.'">Learn More</a>',
									'location' => $url
								);
							}

							$loaded_pages++;
						}
					}
				}
			}
		}

		if(!empty($errors))
		{
			return array(
				'pass' => false,
				'errors' => $errors,
				'message' => 'There were ('.count($errors).') pages that did not pass the Speed Test'
			);
		}

		if(!$loaded_pages)
		{
			return array(
				'pass' => null,
				'message' => 'Unable to connect to Google Page Speed Insights API.'
			);
		}

		return array(
			'pass' => true,
			'message' => 'Overall Page Speed rating is ('.round($total_score/$loaded_pages).').'
		);
	}
}