<?php

class GRAV_TEST_JS_ERRORS
{
	public function type()
	{
		return 'js';
	}

	public function environment()
	{
		return 'all';
	}

	public function group()
	{
		return 'JS Tests';
	}

	public function label()
	{
		return 'JS Errors';
	}

	public function description()
	{
		return 'Check General Pages for JS Errors on Page Load';
	}

	public function js_urls()
	{
		$urls = GRAV_TESTS::get_general_page_urls();
		$js_urls = array();
		foreach ($urls as $url)
		{
			$js_urls[] = array('url' => $url, 'with_admin_bar' => false, 'width' => 800, 'height' => 600);
		}
		return $js_urls;
	}

	public function js_head()
	{
		?>
		<script type="text/javascript">
			var _grav_test_page_has_js_error = false;
			window.onerror = function(error, file, linenumber)
			{
				alert(error, file, linenumber);
				_grav_test_page_has_js_error = true;
		  		parent.grav_tests_js_pass('<?php echo $this->id;?>', false, 'JS Error loading ('+window.location.href+'): '+ error, file+' (Line: '+linenumber+')');
			};

			setTimeout(function(){
				if(_grav_test_page_has_js_error !== true)
				{
		  			parent.grav_tests_js_pass('<?php echo $this->id;?>', true, 'No JS Errors Detected', '');
		  		}
		  	}, 10000);
		</script>
		<?php
	}

	/*
	public function js_footer()
	{
		?>
		<!-- Nothing -->
		<?php
	}
	*/
}