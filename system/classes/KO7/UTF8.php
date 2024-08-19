<?php
/**
 * A port of [phputf8](http://phputf8.sourceforge.net/) to a unified set
 * of files. Provides multi-byte aware replacement string functions.
 *
 * For UTF-8 support to work correctly, the following requirements must be met:
 *
 * - PCRE needs to be compiled with UTF-8 support (--enable-utf8)
 * - Support for [Unicode properties](http://php.net/manual/reference.pcre.pattern.modifiers.php)
 *   is highly recommended (--enable-unicode-properties)
 * - The [mbstring extension](http://php.net/mbstring) is highly recommended,
 *   but must not be overloading string functions
 *
 * [!!] This file is licensed differently from the rest of KO7. As a port of
 * [phputf8](http://phputf8.sourceforge.net/), this file is released under the LGPL.
 *
 * @package    KO7
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */
class KO7_UTF8 {

	/**
	 * @var bool Does the server support UTF-8 natively?
	 */
	public static $server_utf8;

	/**
	 * @var array<string, bool> List of called methods that have had their required file included.
	 */
	public static $called = [];

    /**
     * Recursively cleans arrays, objects, and strings. Removes ASCII control
     * codes and converts to the requested charset while silently discarding
     * incompatible characters.
     *
     *     UTF8::clean($_GET); // Clean GET data
     *
     * @template TData
     *
     * @param TData       $var     variable to clean
     * @param string|null $charset character set, defaults to KO7::$charset
     *
     * @return TData
     *
     * @uses UTF8::strip_ascii_ctrl
     * @uses UTF8::is_ascii
     */
	public static function clean($var, ?string $charset = NULL)
	{
		if ( ! $charset)
		{
			// Use the application character set
			$charset = KO7::$charset;
		}
		
		if (is_iterable($var))
		{
			$vars = [];
			foreach ($var as $key => $val)
			{
				$vars[UTF8::clean($key, $charset)] = UTF8::clean($val, $charset);
			}
			$var = $vars;
		}
		elseif (is_string($var) AND $var !== '')
		{
			// Remove control characters
			$var = UTF8::strip_ascii_ctrl($var);
			if ( ! UTF8::is_ascii($var))
			{
				// Temporarily save the mb_substitute_character() value into a variable
				$substitute_character = mb_substitute_character();
				// Disable substituting illegal characters with the default '?' character
				mb_substitute_character('none');
				// convert encoding, this is expensive, used when $var is not ASCII
				$var = mb_convert_encoding($var, $charset, $charset);
				// Reset mb_substitute_character() value back to the original setting
				mb_substitute_character($substitute_character);
			}
		}
		
		return $var;
	}

    /**
     * Tests whether a string contains only 7-bit ASCII bytes. This is used to
     * determine when to use native functions or UTF-8 functions.
     *
     *     $ascii = UTF8::is_ascii($str);
     *
     * @param string|string[] $str string or array of strings to check
     *
     * @return bool
     */
	public static function is_ascii($str)
	{
		if (is_array($str))
		{
			$str = implode($str);
		}

		return ! preg_match('/[^\x00-\x7F]/S', (string) $str);
	}

    /**
     * Strips out device control codes in the ASCII range.
     *
     *     $str = UTF8::strip_ascii_ctrl($str);
     *
     * @param string $str string to clean
     *
     * @return  string
     */
	public static function strip_ascii_ctrl($str)
	{
		return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', (string) $str);
	}

    /**
     * Strips out all non-7bit ASCII bytes.
     *
     *     $str = UTF8::strip_non_ascii($str);
     *
     * @param string $str string to clean
     *
     * @return string
     */
	public static function strip_non_ascii($str)
	{
		return preg_replace('/[^\x00-\x7F]+/S', '', (string) $str);
	}

    /**
     * Replaces special/accented UTF-8 characters by ASCII-7 "equivalents".
     *
     *     $ascii = UTF8::transliterate_to_ascii($utf8);
     *
     * @param string $str string to transliterate
     * @param int    $case -1 lowercase only, +1 uppercase only, 0 both cases
     *
     * @return  string
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
	public static function transliterate_to_ascii($str, int $case = 0)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _transliterate_to_ascii((string) $str, $case);
	}

    /**
     * Returns the length of the given string. This is a UTF8-aware version
     * of [strlen](http://php.net/strlen).
     *
     *     $length = UTF8::strlen($str);
     *
     * @param string $str string being measured for length
     *
     * @return int
     *
     * @uses UTF8::$server_utf8
     * @uses KO7::$charset
     */
	public static function strlen($str)
	{
		if (UTF8::$server_utf8)
			return mb_strlen((string) $str, KO7::$charset);

		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strlen((string) $str);
	}

    /**
     * Finds position of first occurrence of a UTF-8 string. This is a
     * UTF8-aware version of [strpos](http://php.net/strpos).
     *
     *     $position = UTF8::strpos($str, $search);
     *
     * @param string $str    haystack
     * @param string $search needle
     * @param int    $offset offset from which character in haystack to start searching
     *
     * @return int|bool position of needle or FALSE if the needle is not found
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     *
     * @uses UTF8::$server_utf8
     * @uses KO7::$charset
     */
	public static function strpos($str, $search, int $offset = 0)
	{
		if (UTF8::$server_utf8)
			return mb_strpos((string) $str, (string) $search, $offset, KO7::$charset);

		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strpos((string) $str, (string) $search, $offset);
	}

    /**
     * Finds position of last occurrence of a char in a UTF-8 string. This is
     * a UTF8-aware version of [strrpos](http://php.net/strrpos).
     *
     *     $position = UTF8::strrpos($str, $search);
     *
     * @param string $str    haystack
     * @param string $search needle
     * @param int    $offset offset from which character in haystack to start searching
     *
     * @return int|bool position of needle or FALSE if the needle is not found
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     *
     * @uses UTF8::$server_utf8
     */
	public static function strrpos($str, $search, int $offset = 0)
	{
		if (UTF8::$server_utf8)
			return mb_strrpos((string) $str, (string) $search, $offset, KO7::$charset);

		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strrpos((string) $str, (string) $search, $offset);
	}

    /**
     * Returns part of a UTF-8 string. This is a UTF8-aware version
     * of [substr](http://php.net/substr).
     *
     *     $sub = UTF8::substr($str, $offset);
     *
     * @param string   $str    input string
     * @param int      $offset offset
     * @param int|null $length length limit
     *
     * @return string
     *
     * @author Chris Smith <chris@jalakai.co.uk>
     *
     * @uses UTF8::$server_utf8
     * @uses KO7::$charset
     */
	public static function substr($str, int $offset, ?int $length = NULL)
	{
		$str = (string) $str;
		if (UTF8::$server_utf8)
		{
			return mb_substr($str, $offset, $length ?? mb_strlen($str, KO7::$charset), KO7::$charset);
		}

		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _substr($str, $offset, $length);
	}

    /**
     * Replaces text within a portion of a UTF-8 string. This is a UTF8-aware
     * version of [substr_replace](http://php.net/substr_replace).
     *
     *     $str = UTF8::substr_replace($str, $replacement, $offset);
     *
     * @param string   $str         input string
     * @param string   $replacement replacement string
     * @param int      $offset      offset
     * @param int|null $length      length
     *
     * @return string
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function substr_replace($str, $replacement, int $offset, ?int $length = NULL)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _substr_replace((string) $str, (string) $replacement, $offset, $length);
	}

    /**
     * Makes a UTF-8 string lowercase. This is a UTF8-aware version
     * of [strtolower](http://php.net/strtolower).
     *
     *     $str = UTF8::strtolower($str);
     *
     * @param string $str mixed case string
     *
     * @return string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @uses UTF8::$server_utf8
     * @uses KO7::$charset
     */
	public static function strtolower($str)
	{
		if (UTF8::$server_utf8)
			return mb_strtolower((string) $str, KO7::$charset);

		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strtolower((string) $str);
	}

    /**
     * Makes a UTF-8 string uppercase. This is a UTF8-aware version
     * of [strtoupper](http://php.net/strtoupper).
     *
     * @param string $str mixed case string
     *
     * @return  string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     *
     * @uses UTF8::$server_utf8
     * @uses KO7::$charset
     */
	public static function strtoupper($str)
	{
		if (UTF8::$server_utf8)
			return mb_strtoupper((string) $str, KO7::$charset);

		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strtoupper((string) $str);
	}

    /**
     * Makes a UTF-8 string's first character uppercase. This is a UTF8-aware
     * version of [ucfirst](http://php.net/ucfirst).
     *
     *     $str = UTF8::ucfirst($str);
     *
     * @param string $str mixed case string
     *
     * @return string
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function ucfirst($str)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _ucfirst((string) $str);
	}

    /**
     * Makes the first character of every word in a UTF-8 string uppercase.
     * This is a UTF8-aware version of [ucwords](http://php.net/ucwords).
     *
     *     $str = UTF8::ucwords($str);
     *
     * @param string $str mixed case string
     *
     * @return string
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function ucwords($str)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _ucwords((string) $str);
	}

    /**
     * Case-insensitive UTF-8 string comparison. This is a UTF8-aware version
     * of [strcasecmp](http://php.net/strcasecmp).
     *
     *     $compare = UTF8::strcasecmp($str1, $str2);
     *
     * @param string $str1 string to compare
     * @param string $str2 string to compare
     *
     * @return  int less than 0, 0 or greater than 0 if str1 is less than, equal, greater than str2 respectively
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
	public static function strcasecmp($str1, $str2)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strcasecmp((string) $str1, (string) $str2);
	}

    /**
     * Returns a string or an array with all occurrences of search in subject
     * (ignoring case) and replaced with the given replace value. This is a
     * UTF8-aware version of [str_ireplace](http://php.net/str_ireplace).
     *
     * [!!] This function is very slow compared to the native version. Avoid
     * using it when possible.
     *
     * @param string|array $search  text to replace
     * @param string|array $replace replacement text
     * @param string|array $str     subject text
     * @param int          $count   number of matched and replaced needles will be returned
     *                              via this parameter which is passed by reference
     *
     * @return string|array depending on the input type
     *
     * @author  Harry Fuecks <hfuecks@gmail.com
     */
	public static function str_ireplace($search, $replace, $str, int & $count = 0)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _str_ireplace($search, $replace, $str, $count);
	}

    /**
     * Case-insensitive UTF-8 version of strstr. Returns all of input string
     * from the first occurrence of needle to the end. This is a UTF8-aware
     * version of [stristr](http://php.net/stristr).
     *
     *     $found = UTF8::stristr($str, $search);
     *
     * @param string $str    input string
     * @param string $search needle
     *
     * @return  string|bool matched substring if found, or FALSE if not found
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
	public static function stristr($str, $search)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _stristr((string) $str, (string) $search);
	}

    /**
     * Finds the length of the initial segment matching mask. This is a
     * UTF8-aware version of [strspn](http://php.net/strspn).
     *
     *     $found = UTF8::strspn($str, $mask);
     *
     * @param string   $str    input string
     * @param string   $mask   mask for search
     * @param int|null $offset start position of the string to examine
     * @param int|null $length length of the string to examine
     *
     * @return int length of the initial segment that contains characters in the mask
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function strspn($str, $mask, ?int $offset = NULL, ?int $length = NULL)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strspn((string) $str, (string) $mask, $offset, $length);
	}

    /**
     * Finds the length of the initial segment not matching mask. This is a
     * UTF8-aware version of [strcspn](http://php.net/strcspn).
     *
     *     $found = UTF8::strcspn($str, $mask);
     *
     * @param string   $str    input string
     * @param string   $mask   mask for search
     * @param int|null $offset start position of the string to examine
     * @param int|null $length length of the string to examine
     *
     * @return int length of the initial segment that contains characters not in the mask
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function strcspn($str, $mask, ?int $offset = NULL, ?int $length = NULL)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strcspn((string) $str, (string) $mask, $offset, $length);
	}

    /**
     * Pads a UTF-8 string to a certain length with another string. This is a
     * UTF8-aware version of [str_pad](http://php.net/str_pad).
     *
     *     $str = UTF8::str_pad($str, $length);
     *
     * @param string $str              input string
     * @param int    $final_str_length desired string length after padding
     * @param string $pad_str          string to use as padding
     * @param int    $pad_type         padding type: STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH
     *
     * @return string
     *
     * @throws UTF8_Exception
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function str_pad($str, int $final_str_length, string $pad_str = ' ', int $pad_type = STR_PAD_RIGHT)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _str_pad((string) $str, $final_str_length, $pad_str, $pad_type);
	}

    /**
     * Converts a UTF-8 string to an array. This is a UTF8-aware version of
     * [str_split](http://php.net/str_split).
     *
     *     $array = UTF8::str_split($str);
     *
     * @param string $str          input string
     * @param int    $split_length maximum length of each chunk
     *
     * @return string[]|bool
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function str_split($str, int $split_length = 1)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _str_split((string) $str, $split_length);
	}

    /**
     * Reverses a UTF-8 string. This is a UTF8-aware version of [strrev](http://php.net/strrev).
     *
     *     $str = UTF8::strrev($str);
     *
     * @param string $str string to be reversed
     *
     * @return string
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function strrev($str)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _strrev((string) $str);
	}

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning and
     * end of a string. This is a UTF8-aware version of [trim](http://php.net/trim).
     *
     *     $str = UTF8::trim($str);
     *
     * @param string      $str      input string
     * @param string|null $charlist string of characters to remove
     *
     * @return string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
	public static function trim($str, ?string $charlist = NULL)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _trim((string) $str, $charlist);
	}

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning of
     * a string. This is a UTF8-aware version of [ltrim](http://php.net/ltrim).
     *
     *     $str = UTF8::ltrim($str);
     *
     * @param string      $str      input string
     * @param string|null $charlist string of characters to remove
     *
     * @return string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
	public static function ltrim($str, ?string $charlist = NULL)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _ltrim((string) $str, $charlist);
	}

    /**
     * Strips whitespace (or other UTF-8 characters) from the end of a string.
     * This is a UTF8-aware version of [rtrim](http://php.net/rtrim).
     *
     *     $str = UTF8::rtrim($str);
     *
     * @param string      $str      input string
     * @param string|null $charlist string of characters to remove
     *
     * @return string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
	public static function rtrim($str, ?string $charlist = NULL)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _rtrim((string) $str, $charlist);
	}

    /**
     * Returns the unicode ordinal for a character. This is a UTF8-aware
     * version of [ord](http://php.net/ord).
     *
     *     $digit = UTF8::ord($character);
     *
     * @param string $chr UTF-8 encoded character
     *
     * @return int
     *
     * @throws UTF8_Exception
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     */
	public static function ord($chr)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _ord((string) $chr);
	}

    /**
     * Takes an UTF-8 string and returns an array of ints representing the Unicode characters.
     * Astral planes are supported i.e. the ints in the output can be > 0xFFFF.
     * Occurrences of the BOM are ignored. Surrogates are not allowed.
     *
     *     $array = UTF8::to_unicode($str);
     *
     * The Original Code is Mozilla Communicator client code.
     * The Initial Developer of the Original Code is Netscape Communications Corporation.
     * Portions created by the Initial Developer are Copyright (C) 1998 the Initial Developer.
     * Ported to PHP by Henri Sivonen <hsivonen@iki.fi>, see <http://hsivonen.iki.fi/php-utf8/>
     * Slight modifications to fit with phputf8 library by Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str UTF-8 encoded string
     *
     * @return int[]|bool Unicode code points or FALSE if the string is invalid
     *
     * @throws UTF8_Exception
     */
	public static function to_unicode($str)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _to_unicode((string) $str);
	}

    /**
     * Takes an array of ints representing the Unicode characters and returns a UTF-8 string.
     * Astral planes are supported i.e. the ints in the input can be > 0xFFFF.
     * Occurrences of the BOM are ignored. Surrogates are not allowed.
     *
     *     $str = UTF8::to_unicode($array);
     *
     * The Original Code is Mozilla Communicator client code.
     * The Initial Developer of the Original Code is Netscape Communications Corporation.
     * Portions created by the Initial Developer are Copyright (C) 1998 the Initial Developer.
     * Ported to PHP by Henri Sivonen <hsivonen@iki.fi>, see http://hsivonen.iki.fi/php-utf8/
     * Slight modifications to fit with phputf8 library by Harry Fuecks <hfuecks@gmail.com>.
     *
     * @param int[] $arr unicode code points representing a string
     *
     * @return string|bool UTF-8 string of characters or FALSE if a code point cannot be found
     *
     * @throws UTF8_Exception
     */
	public static function from_unicode($arr)
	{
		if ( ! isset(UTF8::$called[__FUNCTION__]))
		{
			require KO7::find_file('utf8', __FUNCTION__);

			// Function has been called
			UTF8::$called[__FUNCTION__] = TRUE;
		}

		return _from_unicode($arr);
	}

}

if (KO7_UTF8::$server_utf8 === NULL)
{
	// Determine if this server supports UTF-8 natively
	KO7_UTF8::$server_utf8 = extension_loaded('mbstring');
}
