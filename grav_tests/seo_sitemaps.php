<?php

class GRAV_TEST_SEO_SITEMAPS
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
		return 'SEO Sitemaps';
	}

	public function description()
	{
		return 'Check for both /sitemap and /sitemap.xml';
	}

	private function get_http_response_code($theURL)
	{
	    $headers = get_headers($theURL);
	    return substr($headers[0], 9, 3);
	}

	public function run()
	{
		$errors = array();
		if(intval($this->get_http_response_code(site_url().'/sitemap/')) !== 200 && intval($this->get_http_response_code(site_url().'/sitemap.html')) !== 200)
		{
			$errors[] = array('pass' => false, 'message' => 'your site is missing an HTML (sitemap or sitemap.html) page', 'location' => site_url().'/sitemap/');
		}

		if(intval($this->get_http_response_code(site_url().'/sitemap.xml')) !== 200)
		{
			$errors[] = array('pass' => false, 'message' => 'your site is missing an XML site map page', 'location' => site_url().'/sitemap.xml');
		}

		if(empty($errors))
		{
			return array('pass' => true, 'message' => 'Successfully Found both HTML and XML Sitemap Pages');
		}
		else
		{
			return array('pass' => false, 'message' => 'Error finding Sitemap Pages', 'errors' => $errors);
		}
	}
}