<?php
/**
 * Inflector helper class. Inflection is changing the form of a word based on
 * the context it is used in. For example, changing a word into a plural form.
 *
 * [!!] Inflection is only tested with English, and is will not work with other languages.
 *
 * @package    KO7
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Inflector {

	/**
	 * @var  array<string, string>  cached inflections
	 */
	protected static $cache = [];

	/**
	 * @var  array<string, string>  uncountable words
	 */
	protected static $uncountable;

	/**
	 * @var  array<string, string>  irregular words
	 */
	protected static $irregular;

	/**
	 * Checks if a word is defined as uncountable. An uncountable word has a
	 * single form. For instance, one "fish" and many "fish", not "fishes".
	 *
	 *     Inflector::uncountable('fish'); // TRUE
	 *     Inflector::uncountable('cat');  // FALSE
	 *
	 * If you find a word is being pluralized improperly, it has probably not
	 * been defined as uncountable in `config/inflector.php`. If this is the
	 * case, please report [an issue](http://koseven.dev/projects/KO7/issues).
	 *
	 * @param   string  $str    word to check
	 *
	 * @return  boolean
	 */
	public static function uncountable($str)
	{
		if (Inflector::$uncountable === NULL)
		{
			// Cache uncountables
			Inflector::$uncountable = KO7::$config->load('inflector')->uncountable;

			// Make uncountables mirrored
			Inflector::$uncountable = array_combine(Inflector::$uncountable, Inflector::$uncountable);
		}

		return isset(Inflector::$uncountable[strtolower($str)]);
	}

	/**
	 * Makes a plural word singular.
	 *
	 *     echo Inflector::singular('cats'); // "cat"
	 *     echo Inflector::singular('fish'); // "fish", uncountable
	 *
	 * You can also provide the count to make inflection more intelligent.
	 * In this case, it will only return the singular value if the count is
	 * greater than one and not zero.
	 *
	 *     echo Inflector::singular('cats', 2); // "cats"
	 *
	 * [!!] Special inflections are defined in `config/inflector.php`.
	 *
	 * @uses    Inflector::uncountable
	 *
	 * @param   string       $str   word to make singular
	 * @param   integer|null $count count of thing
	 *
	 * @return  string
	 */
	public static function singular($str, $count = NULL)
	{
		// $count should always be a float
		$count = ($count === NULL) ? 1.0 : (float) $count;

		// Do nothing when $count is not 1
		if ($count != 1)
			return $str;

		// Remove garbage
		$str = strtolower(trim($str));

		// Cache key name
		$key = 'singular_'.$str.$count;

		if (isset(Inflector::$cache[$key]))
			return Inflector::$cache[$key];

		if (Inflector::uncountable($str))
			return Inflector::$cache[$key] = $str;

		if (empty(Inflector::$irregular))
		{
			// Cache irregular words
			Inflector::$irregular = KO7::$config->load('inflector')->irregular;
		}

		if ($irregular = array_search($str, Inflector::$irregular))
		{
			$str = $irregular;
		}
		elseif (preg_match('/us$/', $str))
		{
			// http://en.wikipedia.org/wiki/Plural_form_of_words_ending_in_-us
			// Already singular, do nothing
		}
		elseif (preg_match('/[sxz]es$/', $str) OR preg_match('/[^aeioudgkprt]hes$/', $str))
		{
			// Remove "es"
			$str = substr($str, 0, -2);
		}
		elseif (preg_match('/[^aeiou]ies$/', $str))
		{
			// Replace "ies" with "y"
			$str = substr($str, 0, -3).'y';
		}
		elseif (substr($str, -1) === 's' AND substr($str, -2) !== 'ss')
		{
			// Remove singular "s"
			$str = substr($str, 0, -1);
		}

		return Inflector::$cache[$key] = $str;
	}

	/**
	 * Makes a singular word plural.
	 *
	 *     echo Inflector::plural('fish'); // "fish", uncountable
	 *     echo Inflector::plural('cat');  // "cats"
	 *
	 * You can also provide the count to make inflection more intelligent.
	 * In this case, it will only return the plural value if the count is
	 * not one.
	 *
	 *     echo Inflector::singular('cats', 3); // "cats"
	 *
	 * [!!] Special inflections are defined in `config/inflector.php`.
	 *
	 * @uses    Inflector::uncountable
	 *
	 * @param   string       $str    word to pluralize
	 * @param   integer|null $count  count of thing
	 *
	 * @return  string
	 */
	public static function plural($str, $count = NULL)
	{
		// $count should always be a float
		$count = ($count === NULL) ? 0.0 : (float) $count;

		// Do nothing with singular
		if ($count == 1)
			return $str;

		// Remove garbage
		$str = trim($str);

		// Cache key name
		$key = 'plural_'.$str.$count;

		// Check uppercase
		$is_uppercase = ctype_upper($str);

		if (isset(Inflector::$cache[$key]))
			return Inflector::$cache[$key];

		if (Inflector::uncountable($str))
			return Inflector::$cache[$key] = $str;

		if (empty(Inflector::$irregular))
		{
			// Cache irregular words
			Inflector::$irregular = KO7::$config->load('inflector')->irregular;
		}

		if (isset(Inflector::$irregular[$str]))
		{
			$str = Inflector::$irregular[$str];
		}
		elseif (in_array($str, Inflector::$irregular))
		{
			// Do nothing
		}
		elseif (preg_match('/[sxz]$/', $str) OR preg_match('/[^aeioudgkprt]h$/', $str))
		{
			$str .= 'es';
		}
		elseif (preg_match('/[^aeiou]y$/', $str))
		{
			// Change "y" to "ies"
			$str = substr_replace($str, 'ies', -1);
		}
		else
		{
			$str .= 's';
		}

		// Convert to uppercase if necessary
		if ($is_uppercase)
		{
			$str = strtoupper($str);
		}

		// Set the cache and return
		return Inflector::$cache[$key] = $str;
	}

	/**
	 * Makes a phrase camel case. Spaces and underscores will be removed.
	 *
	 *     $str = Inflector::camelize('mother cat');     // "motherCat"
	 *     $str = Inflector::camelize('kittens in bed'); // "kittensInBed"
	 *
	 * @param   string  $str    phrase to camelize
	 *
	 * @return  string
	 */
	public static function camelize($str)
	{
		$str = 'x'.strtolower(trim($str));
		$str = ucwords(preg_replace('/[\s_]+/', ' ', $str));

		return substr(str_replace(' ', '', $str), 1);
	}

	/**
	 * Converts a camel case phrase into a spaced phrase.
	 *
	 *     $str = Inflector::decamelize('houseCat');    // "house cat"
	 *     $str = Inflector::decamelize('kingAllyCat'); // "king ally cat"
	 *
	 * @param   string  $str    phrase to camelize
	 * @param   string  $sep    word separator
	 *
	 * @return  string
	 */
	public static function decamelize($str, $sep = ' ')
	{
		return strtolower(preg_replace('/([a-z])([A-Z])/', '$1'.$sep.'$2', trim($str)));
	}

	/**
	 * Makes a phrase underscored instead of spaced.
	 *
	 *     $str = Inflector::underscore('five cats'); // "five_cats";
	 *
	 * @param   string  $str    phrase to underscore
	 *
	 * @return  string
	 */
	public static function underscore($str)
	{
		return preg_replace('/\s+/', '_', trim($str));
	}

	/**
	 * Makes an underscored or dashed phrase human-readable.
	 *
	 *     $str = Inflector::humanize('kittens-are-cats'); // "kittens are cats"
	 *     $str = Inflector::humanize('dogs_as_well');     // "dogs as well"
	 *
	 * @param   string  $str    phrase to make human-readable
	 *
	 * @return  string
	 */
	public static function humanize($str)
	{
		return preg_replace('/[_-]+/', ' ', trim($str));
	}

}