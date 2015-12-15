<?php

class GRAVITATE_TEST_WP_HEAD_FOOTER
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
		return 'Check for wp_head() and wp_footer()';
	}

	public function run()
	{
		$header = file_get_contents(get_template_directory().'/header.php');
		$has_head = false;

		if($header)
		{
			$header = preg_replace('!/\*.*?\*/!s', '', $header);
			$header = preg_replace('/\n\s*\n/', "\n", $header);
			$header = preg_replace('![ \t]*//.*[ \t]*[\r\n]!', '', $header);
		}
		else
		{
			return array('pass' => false, 'message' => 'Your Theme seems to be missing "header.php"', 'location' => '');
		}

		if(strpos($header, 'wp_head()') !== false || strpos($header, 'wp_head ()') !== false)
		{
			$has_head = true;
		}
		else
		{
			return array('pass' => false, 'message' => 'header.php is missing "wp_head();"', 'location' => get_template_directory().'/header.php');
		}

		$footer = file_get_contents(get_template_directory().'/footer.php');
		$has_footer = false;

		if($footer)
		{
			$footer = preg_replace('!/\*.*?\*/!s', '', $footer);
			$footer = preg_replace('/\n\s*\n/', "\n", $footer);
			$footer = preg_replace('![ \t]*//.*[ \t]*[\r\n]!', '', $footer);
		}
		else
		{
			return array('pass' => false, 'message' => 'Your Theme seems to be missing "footer.php"', 'location' => '');
		}

		if(strpos($footer, 'wp_footer()') !== false || strpos($footer, 'wp_footer ()') !== false)
		{
			$has_footer = true;
		}
		else
		{
			return array('pass' => false, 'message' => 'footer.php is missing "wp_footer();"', 'location' => get_template_directory().'/footer.php');
		}

		if($has_footer && $has_head)
		{
			return array('pass' => true, 'message' => 'Found both wp_head() and wp_footer()', 'location' => '');
		}

		return array('pass' => false, 'message' => 'Error finding wp_head() or wp_footer()', 'location' => '');

	}
}