<?php

class GRAV_TEST_JS_CONSOLE_LOGS
{
	public function type()
	{
		return 'js';
	}

	public function environment()
	{
		return 'staging,production';
	}

	public function group()
	{
		return 'JS Tests';
	}

	public function label()
	{
		return 'JS Console Logs';
	}

	public function description()
	{
		return 'Check General Pages for Console Logs on Page Load';
	}

	public function js_urls()
	{
		$urls = GRAV_TESTS::get_general_page_urls();
		$js_urls = array();
		foreach ($urls as $url)
		{
			$js_urls[] = array('url' => $url, 'with_admin_bar' => false, 'width' => 860, 'height' => 680);
		}
		return $js_urls;
	}

	public function js_head()
	{
		?>
		<script type="text/javascript">

			var _grav_test_page_has_js_log = false;

			var _grav_test_page_has_js_error = false;
			window.onerror = function(error, file, linenumber)
			{
				_grav_test_page_has_js_error = true;
			};

			(function(){
			    var oldLog = console.log;
			    console.log = function (message)
			    {
					_grav_test_page_has_js_log = true;
			  		parent.grav_tests_js_pass('<?php echo $this->id;?>', false, 'Console Log Detected', window.location.href);
			        oldLog.apply(console, arguments);
			    };
			})();

			setTimeout(function(){
				if(_grav_test_page_has_js_log !== true)
				{
					if(_grav_test_page_has_js_error)
					{
						parent.grav_tests_js_pass('<?php echo $this->id;?>', null, 'No Logs Detected, but found Errors. Resolve the Errors first', '');
					}
					else
					{
		  				parent.grav_tests_js_pass('<?php echo $this->id;?>', true, 'No Console Logs Detected', '');
		  			}
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