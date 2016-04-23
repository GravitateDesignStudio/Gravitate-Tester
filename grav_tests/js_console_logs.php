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

	public function wp_head()
	{
		?>
		<script type="text/javascript">

			var _grav_test_page_js_logs = [];
			var _grav_test_page_has_js_error = false;

			window.onerror = function(error, file, linenumber)
			{
				_grav_test_page_has_js_error = true;
				//alert(3);
			};

			(function(){
			    var oldLog = console.log;
			    console.log = function (message)
			    {
			    	var caller_line = (new Error).stack.split('@').join(' > ').split(' at ').join(' > ');

			    	var response = {
						'message': 'Console Log Detected on ('+window.location.href+')',
						'location': caller_line
					};
			  		_grav_test_page_js_logs.push(response);
			    };
			})();

			function grav_send_js_test_results()
			{
				setTimeout(function()
				{
					if(_grav_test_page_js_logs.length > 0)
					{
						var response = {
							'test': '<?php echo $this->id;?>',
							'pass': false,
							'errors': _grav_test_page_js_logs,
							'message': 'Console Logs Detected on ('+window.location.href+')'
						};

			  		}
			  		else if(_grav_test_page_has_js_error)
			  		{
			  			var response = {
							'test': '<?php echo $this->id;?>',
							'pass': null,
							'message': 'No Console Logs Detected, but Errors Found. Fix Errors first.'
						};
			  		}
			  		else
			  		{
			  			var response = {
							'test': '<?php echo $this->id;?>',
							'pass': true,
							'message': 'No Console Logs Detected'
						};
			  		}
			  		parent.grav_tests_js_pass(response);
				}, 2000);
			}

		</script>
		<?php
	}


	public function wp_footer()
	{
		?>
		<script>

		if(typeof jQuery !== 'undefined')
		{
			jQuery(window).on('load', function()
			{
				grav_send_js_test_results();
			});
		}
		else
		{
			if (typeof window.onload != 'function')
			{
				window.onload = grav_send_js_test_results;
			}
			else
			{
				var oldonload = window.onload;
				window.onload = function()
				{
					if(oldonload)
					{
						oldonload();
					}
					grav_send_js_test_results();
				}
			}
		}

		</script>
		<?php
	}

}