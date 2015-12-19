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
			var _grav_test_page_js_errors = [];
			window.onerror = function(error, file, linenumber)
			{
				var response = {
					'message': 'JS Error loading ('+window.location.href+') '+ error,
					'location': file,
					'linenumber': linenumber
				};

				_grav_test_page_js_errors.push(response);
			};

			setTimeout(function(){
				if(_grav_test_page_js_errors.length > 0)
				{
					var response = {
						'test': '<?php echo $this->id;?>',
						'pass': false,
						'errors': _grav_test_page_js_errors,
						'message': 'Detected ('+_grav_test_page_js_errors.length+') JS Errors'
					};

		  		}
		  		else
		  		{
		  			var response = {
						'test': '<?php echo $this->id;?>',
						'pass': true,
						'message': 'No JS Errors Detected'
					};
		  		}
		  		parent.grav_tests_js_pass(response);
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