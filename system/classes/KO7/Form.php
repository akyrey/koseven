<?php
/**
 * Form helper class. Unless otherwise noted, all generated HTML will be made
 * safe using the [HTML::chars] method. This prevents against simple XSS
 * attacks that could otherwise be triggered by inserting HTML characters into
 * form fields.
 *
 * @package    KO7
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Form {

	/**
	 * Generates an opening HTML form tag.
	 *
	 *     // Form will submit back to the current page using POST
	 *     echo Form::open();
	 *
	 *     // Form will submit to 'search' using GET
	 *     echo Form::open('search', array('method' => 'get'));
	 *
	 *     // When "file" inputs are present, you must include the "enctype"
	 *     echo Form::open(NULL, array('enctype' => 'multipart/form-data'));
	 *
	 * @uses    Request
	 * @uses    URL::site
	 * @uses    HTML::attributes
	 *
	 * @param   Request|string|null        $action     form action, defaults to the current request URI, or [Request] class to use
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function open($action = NULL, array $attributes = NULL)
	{
		if ($action instanceof Request)
		{
			// Use the current URI
			$action = $action->uri();
		}

		if ( ! $action)
		{
			// Allow empty form actions (submits back to the current url).
			$action = '';
		}
		elseif (strpos($action, '://') === FALSE AND strncmp($action, '//', 2))
		{
			// Make the URI absolute
			$action = URL::site($action);
		}

		// Add the form action to the attributes
		$attributes['action'] = $action;

		// Only accept the default character set
		$attributes['accept-charset'] = KO7::$charset;

		if ( ! isset($attributes['method']))
		{
			// Use POST method
			$attributes['method'] = 'post';
		}

		return '<form'.HTML::attributes($attributes).'>';
	}

	/**
	 * Creates the closing form tag.
	 *
	 *     echo Form::close();
	 *
	 * @return  string
	 */
	public static function close()
	{
		return '</form>';
	}

	/**
	 * Creates a form input. If no type is specified, a "text" type input will
	 * be returned.
	 *
	 *     echo Form::input('username', $username);
	 *
	 * @uses    HTML::attributes
	 *
	 * @param   string                     $name       input name
	 * @param   string|null                $value      input value
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function input($name, $value = NULL, array $attributes = NULL)
	{
		// Set the input name
		$attributes['name'] = $name;

		// Set the input value
		$attributes['value'] = $value;

		if ( ! isset($attributes['type']))
		{
			// Default type is text
			$attributes['type'] = 'text';
		}

		return '<input'.HTML::attributes($attributes).' />';
	}

	/**
	 * Creates a hidden form input.
	 *
	 *     echo Form::hidden('csrf', $token);
	 *
	 * @uses    Form::input
	 *
	 * @param   string                      $name       input name
	 * @param   string|null                 $value      input value
	 * @param   array<string, string>|null  $attributes html attributes
	 *
	 * @return  string
	 */
	public static function hidden($name, $value = NULL, array $attributes = NULL)
	{
		$attributes['type'] = 'hidden';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a password form input.
	 *
	 *     echo Form::password('password');
	 *
	 * @uses    Form::input
	 *
	 * @param   string                      $name       input name
	 * @param   string|null                 $value      input value
	 * @param   array<string, string>|null  $attributes html attributes
	 *
	 * @return  string
	 */
	public static function password($name, $value = NULL, array $attributes = NULL)
	{
		$attributes['type'] = 'password';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a file upload form input. No input value can be specified.
	 *
	 *     echo Form::file('image');
	 *
	 * @uses    Form::input
	 *
	 * @param   string                     $name       input name
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function file($name, array $attributes = NULL)
	{
		$attributes['type'] = 'file';

		return Form::input($name, NULL, $attributes);
	}

	/**
	 * Creates a checkbox form input.
	 *
	 *     echo Form::checkbox('remember_me', 1, (bool) $remember);
	 *
	 * @uses    Form::input
	 *
	 * @param   string                     $name       input name
	 * @param   string|null                $value      input value
	 * @param   boolean                    $checked    checked status
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function checkbox($name, $value = NULL, $checked = FALSE, array $attributes = NULL)
	{
		$attributes['type'] = 'checkbox';

		if ($checked === TRUE)
		{
			// Make the checkbox active
			$attributes[] = 'checked';
		}

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a radio form input.
	 *
	 *     echo Form::radio('like_cats', 1, $cats);
	 *     echo Form::radio('like_cats', 0, ! $cats);
	 *
	 * @uses    Form::input
	 *
	 * @param   string                     $name       input name
	 * @param   string|null                $value      input value
	 * @param   boolean                    $checked    checked status
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function radio($name, $value = NULL, $checked = FALSE, array $attributes = NULL)
	{
		$attributes['type'] = 'radio';

		if ($checked === TRUE)
		{
			// Make the radio active
			$attributes[] = 'checked';
		}

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a textarea form input.
	 *
	 *     echo Form::textarea('about', $about);
	 *
	 * @uses    HTML::attributes
	 * @uses    HTML::chars
	 *
	 * @param   string                     $name           textarea name
	 * @param   string                     $body           textarea body
	 * @param   array<string, string>|null $attributes     html attributes
	 * @param   boolean                    $double_encode  encode existing HTML characters
	 *
	 * @return  string
	 */
	public static function textarea($name, $body = '', array $attributes = NULL, $double_encode = TRUE)
	{
		// Set the input name
		$attributes['name'] = $name;

		// Add default rows and cols attributes (required)
		$attributes += ['rows' => 10, 'cols' => 50];

		return '<textarea'.HTML::attributes($attributes).'>'.HTML::chars($body, $double_encode).'</textarea>';
	}

	/**
	 * Creates a select form input.
	 *
	 *     echo Form::select('country', $countries, $country);
	 *
	 * [!!] Support for multiple selected options was added in v3.0.7.
	 *
	 * @uses    HTML::attributes
	 *
	 * @template TKey
	 * @template TValue
	 *
	 * @param   string                     $name       input name
	 * @param   array<TKey, TValue>        $options    available options
	 * @param   TKey|TKey[]|null           $selected   selected option string, or an array of selected options
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function select($name, array $options = NULL, $selected = NULL, array $attributes = NULL)
	{
		// Set the input name
		$attributes['name'] = $name;

		if (is_array($selected))
		{
			// This is a multi-select, god save us!
			$attributes[] = 'multiple';
		}

		if ( ! is_array($selected))
		{
			if ($selected === NULL)
			{
				// Use an empty array
				$selected = [];
			}
			else
			{
				// Convert the selected options to an array
				$selected = [ (string) $selected];
			}
		}

		if (empty($options))
		{
			// There are no options
			$options = '';
		}
		else
		{
			foreach ($options as $value => $name)
			{
				if (is_array($name))
				{
					// Create a new optgroup
					$group = ['label' => $value];

					// Create a new list of options
					$_options = [];

					foreach ($name as $_value => $_name)
					{
						// Force value to be string
						$_value = (string) $_value;

						// Create a new attribute set for this option
						$option = ['value' => $_value];

						if (in_array($_value, $selected))
						{
							// This option is selected
							$option[] = 'selected';
						}

						// Change the option to the HTML string
						$_options[] = '<option'.HTML::attributes($option).'>'.HTML::chars($_name, FALSE).'</option>';
					}

					// Compile the options into a string
					$_options = "\n".implode("\n", $_options)."\n";

					$options[$value] = '<optgroup'.HTML::attributes($group).'>'.$_options.'</optgroup>';
				}
				else
				{
					// Force value to be string
					$value = (string) $value;

					// Create a new attribute set for this option
					$option = ['value' => $value];

					if (in_array($value, $selected))
					{
						// This option is selected
						$option[] = 'selected';
					}

					// Change the option to the HTML string
					$options[$value] = '<option'.HTML::attributes($option).'>'.HTML::chars($name, FALSE).'</option>';
				}
			}

			// Compile the options into a single string
			$options = "\n".implode("\n", $options)."\n";
		}

		return '<select'.HTML::attributes($attributes).'>'.$options.'</select>';
	}

	/**
	 * Creates a submit form input.
	 *
	 *     echo Form::submit(NULL, 'Login');
	 *
	 * @uses    Form::input
	 *
	 * @param   string                     $name       input name
	 * @param   string                     $value      input value
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function submit($name, $value, array $attributes = NULL)
	{
		$attributes['type'] = 'submit';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a image form input.
	 *
	 *     echo Form::image(NULL, NULL, array('src' => 'media/img/login.png'));
	 *
	 * @uses    Form::input
	 *
	 * @param   string                     $name       input name
	 * @param   string                     $value      input value
	 * @param   array<string, string>|null $attributes html attributes
	 * @param   boolean                    $index      add index file to URL?
	 *
	 * @return  string
	 */
	public static function image($name, $value, array $attributes = NULL, $index = FALSE)
	{
		if ( ! empty($attributes['src']))
		{
			if (strpos($attributes['src'], '://') === FALSE AND strncmp($attributes['src'], '//', 2))
			{
				// Add the base URL
				$attributes['src'] = URL::base($index).$attributes['src'];
			}
		}

		$attributes['type'] = 'image';

		return Form::input($name, $value, $attributes);
	}

	/**
	 * Creates a button form input. Note that the body of a button is NOT escaped,
	 * to allow images and other HTML to be used.
	 *
	 *     echo Form::button('save', 'Save Profile', array('type' => 'submit'));
	 *
	 * @uses    HTML::attributes
	 *
	 * @param   string                     $name       input name
	 * @param   string                     $body       input value
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function button($name, $body, array $attributes = NULL)
	{
		// Set the input name
		$attributes['name'] = $name;

		return '<button'.HTML::attributes($attributes).'>'.$body.'</button>';
	}

	/**
	 * Creates a form label. Label text is not automatically translated.
	 *
	 *     echo Form::label('username', 'Username');
	 *
	 * @uses    HTML::attributes
	 *
	 * @param   string                     $input      target input
	 * @param   string|null                $text       label text, use input name if NULL
	 * @param   array<string, string>|null $attributes html attributes
	 *
	 * @return  string
	 */
	public static function label($input, $text = NULL, array $attributes = NULL)
	{
		if ($text === NULL)
		{
			// Use the input name as the text
			$text = ucwords(preg_replace('/[\W_]+/', ' ', $input));
		}

		// Set the label target
		$attributes['for'] = $input;

		return '<label'.HTML::attributes($attributes).'>'.$text.'</label>';
	}

}