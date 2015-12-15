<?php

class GRAVITATE_TEST_JS_CONSOLE_LOGS
{
	public function type()
	{
		return 'js';
	}

	public function group()
	{
		return 'JS Tests';
	}

	public function description()
	{
		return 'Check General Pages for Console Logs on Page Load';
	}

	public function js_urls()
	{
		$urls = GRAVITATE_TESTER::get_general_page_urls();

		return implode(',', $urls);
	}

	public function js_head()
	{
		?>
		<script type="text/javascript">

			var _grav_test_page_has_js_log = false;

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
		  			parent.grav_tests_js_pass('<?php echo $this->id;?>', true, 'No Console Logs Detected', '');
		  		}
		  	}, 5000);



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