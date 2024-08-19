<?php
/**
 * Cookie helper.
 *
 * @package    KO7
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Cookie {

	/**
	 * @var  string|null  Magic salt to add to the cookie
	 */
	public static $salt = NULL;

	/**
	 * @var  integer  Number of seconds before the cookie expires
	 */
	public static $expiration = 0;

	/**
	 * @var  string  Restrict the path that the cookie is available to
	 */
	public static $path = '/';

	/**
	 * @var  string|null  Restrict the domain that the cookie is available to
	 */
	public static $domain = NULL;

	/**
	 * @var  boolean  Only transmit cookies over secure connections
	 */
	public static $secure = FALSE;

	/**
	 * @var  boolean  Only transmit cookies over HTTP, disabling Javascript access
	 */
	public static $httponly = FALSE;

	/**
	 * Gets the value of a signed cookie. Cookies without signatures will not
	 * be returned. If the cookie signature is present, but invalid, the cookie
	 * will be deleted.
	 *
	 *     // Get the "theme" cookie, or use "blue" if the cookie does not exist
	 *     $theme = Cookie::get('theme', 'blue');
	 *
	 * @template TDefault
	 *
	 * @param   string        $key        cookie name
	 * @param   TDefault|null $default    default value to return
	 *
	 * @return  string|TDefault|null
	 */
	public static function get($key, $default = NULL)
	{
		if ( ! isset($_COOKIE[$key]))
		{
			// The cookie does not exist
			return $default;
		}

		// Get the cookie value
		$cookie = $_COOKIE[$key];

		// Find the position of the split between salt and contents
		$split = strlen(Cookie::salt($key, NULL));

		if (isset($cookie[$split]) AND $cookie[$split] === '~')
		{
			// Separate the salt and the value
			list ($hash, $value) = explode('~', $cookie, 2);

			if (Security::slow_equals(Cookie::salt($key, $value), $hash))
			{
				// Cookie signature is valid
				return $value;
			}

			// The cookie signature is invalid, delete it
			static::delete($key);
		}

		return $default;
	}

	/**
	 * Sets a signed cookie. Note that all cookie values must be strings and no
	 * automatic serialization will be performed!
	 *
	 * [!!] By default, Cookie::$expiration is 0 - if you skip/pass NULL for the optional
	 *      lifetime argument your cookies will expire immediately unless you have separately
	 *      configured Cookie::$expiration.
	 *
	 *
	 *     // Set the "theme" cookie
	 *     Cookie::set('theme', 'red');
	 *
	 * @uses    Cookie::salt
	 *
	 * @param   string       $name       name of cookie
	 * @param   string       $value      value of cookie
	 * @param   integer|null $lifetime   lifetime in seconds
	 *
	 * @return  boolean
	 */
	public static function set($name, $value, $lifetime = NULL)
	{
		if ($lifetime === NULL)
		{
			// Use the default expiration
			$lifetime = Cookie::$expiration;
		}

		if ($lifetime !== 0)
		{
			// The expiration is expected to be a UNIX timestamp
			$lifetime += static::_time();
		}

		// Add the salt to the cookie value
		$value = Cookie::salt($name, $value).'~'.$value;

		return static::_setcookie($name, $value, $lifetime, Cookie::$path, Cookie::$domain, Cookie::$secure, Cookie::$httponly);
	}

	/**
	 * Deletes a cookie by making the value NULL and expiring it.
	 *
	 *     Cookie::delete('theme');
	 *
	 * @param   string  $name   cookie name
	 *
	 * @return  boolean
	 */
	public static function delete($name)
	{
		// Remove the cookie
		unset($_COOKIE[$name]);

		// Nullify the cookie and make it expire
		return static::_setcookie($name, NULL, -86400, Cookie::$path, Cookie::$domain, Cookie::$secure, Cookie::$httponly);
	}

	/**
	 * Generates a salt string for a cookie based on the name and value.
	 *
	 *     $salt = Cookie::salt('theme', 'red');
	 *
	 * @param   string      $name name of cookie
	 * @param   string|null $value value of cookie
	 *
	 * @throws KO7_Exception if Cookie::$salt is not configured
	 *
	 * @return  string
	 */
	public static function salt($name, $value)
	{
		// Require a valid salt
		if ( ! Cookie::$salt)
		{
			throw new KO7_Exception('A valid cookie salt is required. Please set Cookie::$salt in your bootstrap.php. For more information check the documentation');
		}

		return hash_hmac('sha1', $name.$value.Cookie::$salt, Cookie::$salt);
	}

	/**
	 * Proxy for the native setcookie function - to allow mocking in unit tests so that they do not fail when headers
	 * have been sent.
	 *
	 * @see setcookie
	 *
	 * @param string       $name
	 * @param string|null  $value
	 * @param integer|null $expire
	 * @param string|null  $path
	 * @param string|null  $domain
	 * @param boolean|null $secure
	 * @param boolean|null $httponly
	 *
	 * @return bool
	 */
	protected static function _setcookie($name, $value, $expire, $path, $domain, $secure, $httponly)
	{
		return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
	}

	/**
	 * Proxy for the native time function - to allow mocking of time-related logic in unit tests
	 *
	 * @see    time
	 *
	 * @return int
	 */
	protected static function _time()
	{
		return time();
	}

}
