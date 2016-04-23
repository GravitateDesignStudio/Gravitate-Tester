<?php

class GRAV_TEST_GFORMS_HONEYPOT
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
		return 'Gravity Forms';
	}

	public function label()
	{
		return 'Gravity Forms Honeypot';
	}

	public function description()
	{
		return 'Check to make sure all forms have Spam Filtering enabled.';
	}

	public function can_run()
	{
		if(class_exists('GFAPI') && method_exists('GFAPI', 'get_forms'))
		{
			return true;
		}

		return false;
	}

	public function run()
	{
		$errors = array();

		$number_forms = 0;

		if($this->can_run())
		{
			$forms = GFAPI::get_forms();

			foreach($forms as $form)
			{
				if(!empty($form['is_active']))
				{
					if(empty($form['enableHoneypot']))
					{
						$errors[] = array(
							'message' => 'Form "'.$form['title'].'" (ID:'.$form['id'].') does not have Honeypot enabled.',
							'details' => 'You can enable it by going to the settings of the form in Gravity Forms and check the Enable anti-spam honeypot.  Make sure to check the forms afterwards as this will add a new field that will need to be hidden by css in order to submit properly.'
						);
					}
					$number_forms++;
				}
			}
		}

		if(!empty($errors))
		{
			return array(
				'pass' => false,
				'errors' => $errors,
				'message' => 'There are ('.count($errors).') Forms that do not have Honeypot Enabled.'
			);
		}

		if(!empty($number_forms))
		{
			return array(
				'pass' => true,
				'message' => 'All your forms have Honeypot Enabled.'
			);
		}

		return array(
			'pass' => null,
			'message' => 'Can\'t find any Active Forms.'
		);
	}

	public function can_fix()
	{
		if($this->can_run())
		{
			return true;
		}

		return false;
	}

	public function fix_confirmation()
	{
		return 'Make sure to check the forms afterwards as this will add a new field that will need to be hidden by css in order to submit properly.\n\nClick OK to continue.';
	}

	public function fix()
	{
		if($this->can_fix())
		{
			$forms = GFAPI::get_forms();

			foreach( $forms as $form )
			{
				$form['enableHoneypot'] = 1;
				GFAPI::update_form($form);
			}
		}

		return $this->run();
	}
}