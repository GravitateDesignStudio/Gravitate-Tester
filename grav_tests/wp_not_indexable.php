<?php

class GRAV_TEST_WP_NOT_INDEXABLE
{
	public function type()
	{
		return 'php';
	}

	public function environment()
	{
		return 'local,dev,staging';
	}

	public function group()
	{
		return 'SEO Tests';
	}

	public function label()
	{
		return 'WP Not Indexable';
	}

	public function description()
	{
		return 'Make sure WordPress is Not Indexable in Dev and Staging';
	}

	public function run()
	{
		if(!get_option('blog_public'))
		{
			return array('pass' => true, 'message' => 'Your Site is not Indexable by Search Engines', 'location' => '');
		}
		else
		{
			return array('pass' => false, 'message' => 'Your site is Searchable', 'location' => '');
		}
	}

	public function can_fix()
	{
		if(get_option('blog_public'))
		{
			return true;
		}
		return false;
	}

	public function fix()
	{
		if(get_option('blog_public'))
		{
			update_option('blog_public', 0);
		}
		return $this->run();
	}
}