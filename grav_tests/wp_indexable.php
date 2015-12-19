<?php

class GRAV_TEST_WP_INDEXABLE
{
	public function type()
	{
		return 'php';
	}

	public function environment()
	{
		return 'production';
	}

	public function group()
	{
		return 'SEO Tests';
	}

	public function label()
	{
		return 'WP Indexable';
	}

	public function description()
	{
		return 'Make sure WordPress is Indexable in Production';
	}

	public function run()
	{
		if(!get_option('blog_public'))
		{
			return array('pass' => false, 'message' => 'Your Site is not Indexable by Search Engines', 'location' => '');
		}
		else
		{
			return array('pass' => true, 'message' => 'Your site is Searchable', 'location' => '');
		}
	}

	public function can_fix()
	{
		if(!get_option('blog_public'))
		{
			return true;
		}
		return false;
	}

	public function fix()
	{
		if(!get_option('blog_public'))
		{
			update_option('blog_public', 1);
		}
		return $this->run();
	}
}