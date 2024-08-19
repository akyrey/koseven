<?php
/**
 * Request. Uses the [Route] class to determine what
 * [Controller] to send the request to.
 *
 * @package    KO7
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Request implements HTTP_Request {

	/**
	 * @var  string  client user agent
	 */
	public static $user_agent = '';

	/**
	 * @var  string  client IP address
	 */
	public static $client_ip = '0.0.0.0';

	/**
	 * @var  string[]  trusted proxy server IPs
	 */
	public static $trusted_proxies = ['127.0.0.1', 'localhost', 'localhost.localdomain'];

	/**
	 * @var  static  main request instance
	 */
	public static $initial;

	/**
	 * @var  static  currently executing request instance
	 */
	public static $current;

	/**
	 * Creates a new request object for the given URI. New requests should be
	 * Created using the [Request::factory] method.
	 *
	 *     $request = Request::factory($uri);
	 *
	 * If $cache parameter is set, the response for the request will attempt to
	 * be retrieved from the cache.
	 *
	 * @uses    Route::all
	 * @uses    Route::matches
	 *
	 * @param   string|bool  $uri              URI of the request
	 * @param   array   $client_params    An array of params to pass to the request client
	 * @param   bool    $allow_external   Allow external requests? (deprecated in 3.3)
	 * @param   array   $injected_routes  An array of routes to use, for testing
	 *
	 * @return  static
	 *
	 * @throws  Request_Exception
	 */
	public static function factory($uri = TRUE, $client_params = [], $allow_external = TRUE, $injected_routes = [])
	{
		// If this is the initial request
		if ( ! Request::$initial)
		{
			$protocol = HTTP::$protocol;

			if (isset($_SERVER['REQUEST_METHOD']))
			{
				// Use the server request method
				$method = $_SERVER['REQUEST_METHOD'];
			}
			else
			{
				// Default to GET requests
				$method = HTTP_Request::GET;
			}

			if (( ! empty($_SERVER['HTTPS']) AND filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN))
			   OR (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
			   	   AND $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
			       AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies))
			{
				// This request is secure
				$secure = TRUE;
			}

			if (isset($_SERVER['HTTP_REFERER']))
			{
				// There is a referrer for this request
				$referrer = $_SERVER['HTTP_REFERER'];
			}

			if (isset($_SERVER['HTTP_USER_AGENT']))
			{
				// Browser type
				Request::$user_agent = $_SERVER['HTTP_USER_AGENT'];
			}

			if (isset($_SERVER['HTTP_X_REQUESTED_WITH']))
			{
				// Typically used to denote AJAX requests
				$requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'];
			}

			if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])
				AND isset($_SERVER['REMOTE_ADDR'])
				AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies)) {

				// If using CloudFlare, client IP address is sent with this header
				Request::$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
			}
			elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
			    AND isset($_SERVER['REMOTE_ADDR'])
			    AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies))
			{
				// Use the forwarded IP address, typically set when the
				// client is using a proxy server.
				// Format: "X-Forwarded-For: client1, proxy1, proxy2"
				$client_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

				Request::$client_ip = array_shift($client_ips);

				unset($client_ips);
			}
			elseif (isset($_SERVER['HTTP_CLIENT_IP'])
			        AND isset($_SERVER['REMOTE_ADDR'])
			        AND in_array($_SERVER['REMOTE_ADDR'], Request::$trusted_proxies))
			{
				// Use the forwarded IP address, typically set when the
				// client is using a proxy server.
				$client_ips = explode(',', $_SERVER['HTTP_CLIENT_IP']);

				Request::$client_ip = trim(end($client_ips));

				unset($client_ips);
			}
			elseif (isset($_SERVER['REMOTE_ADDR']))
			{
				// The remote IP address
				Request::$client_ip = $_SERVER['REMOTE_ADDR'];
			}

			if ($method !== HTTP_Request::GET)
			{
				// Ensure the raw body is saved for future use
				$body = file_get_contents('php://input');
			}

			if ($uri === TRUE)
			{
				// Attempt to guess the proper URI
				$uri = Request::detect_uri();
			}

			$cookies = [];

			if (($cookie_keys = array_keys($_COOKIE)))
			{
				foreach ($cookie_keys as $key)
				{
					$cookies[$key] = Cookie::get($key);
				}
			}

			// Create the instance singleton
			Request::$initial = $request = new Request($uri, $client_params, $allow_external, $injected_routes);

			// Store global GET and POST data in the initial request only
			$request->protocol($protocol)
				->query($_GET)
				->post($_POST);

			if (isset($secure))
			{
				// Set the request security
				$request->secure($secure);
			}

			if (isset($method))
			{
				// Set the request method
				$request->method($method);
			}

			if (isset($referrer))
			{
				// Set the referrer
				$request->referrer($referrer);
			}

			if (isset($requested_with))
			{
				// Apply the requested with variable
				$request->requested_with($requested_with);
			}

			if (isset($body))
			{
				// Set the request body (probably a PUT type)
				$request->body($body);
			}

			if (isset($cookies))
			{
				$request->cookie($cookies);
			}
		}
		else
		{
			$request = new Request($uri, $client_params, $allow_external, $injected_routes);
		}

		return $request;
	}

	/**
	 * Automatically detects the URI of the main request using PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL.
	 *
	 *     $uri = Request::detect_uri();
	 *
	 * @since   3.0.8
	 *
	 * @return  string  URI of the main request
	 *
	 * @throws  KO7_Exception should only happen if no valid URI is found (open an issue on GitHub with server config)
	 */
	public static function detect_uri()
	{
		if ( ! empty($_SERVER['PATH_INFO']))
		{
			// PATH_INFO does not contain the docroot or index
			$uri = $_SERVER['PATH_INFO'];
		}
		else
		{
			// REQUEST_URI and PHP_SELF include the docroot and index

			if (isset($_SERVER['REQUEST_URI']))
			{
				/**
				 * We use REQUEST_URI as the fallback value. The reason
				 * for this is we might have a malformed URL such as:
				 *
				 *  http://localhost/http://example.com/judge.php
				 *
				 * which parse_url can't handle. So rather than leave empty
				 * handed, we'll use this.
				 */
				$uri = $_SERVER['REQUEST_URI'];

				if ($request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
				{
					// Valid URL path found, set it.
					$uri = $request_uri;
				}

				// Decode the request URI
				$uri = rawurldecode($uri);
			}
			elseif (isset($_SERVER['PHP_SELF']))
			{
				$uri = $_SERVER['PHP_SELF'];
			}
			elseif (isset($_SERVER['REDIRECT_URL']))
			{
				$uri = $_SERVER['REDIRECT_URL'];
			}
			else
			{
				// If you ever see this error, please report an issue at http://koseven.dev/projects/KO7/issues
				// along with any relevant information about your web server setup. Thanks!
				throw new KO7_Exception('Unable to detect the URI using PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL');
			}

			// Get the path from the base URL, including the index file
			$base_url = parse_url(KO7::$base_url, PHP_URL_PATH);

			if (strpos($uri, $base_url) === 0)
			{
				// Remove the base URL from the URI
				$uri = (string) substr($uri, strlen($base_url));
			}

			if (KO7::$index_file AND strpos($uri, KO7::$index_file) === 0)
			{
				// Remove the index file from the URI
				$uri = (string) substr($uri, strlen(KO7::$index_file));
			}
		}

		return $uri;
	}

	/**
	 * Return the currently executing request. This is changed to the current
	 * request when [Request::execute] is called and restored when the request
	 * is completed.
	 *
	 *     $request = Request::current();
	 *
	 * @since   3.0.5
	 *
	 * @return  static
	 */
	public static function current()
	{
		return Request::$current;
	}

	/**
	 * Returns the first request encountered by this framework. This will should
	 * only be set once during the first [Request::factory] invocation.
	 *
	 *     // Get the first request
	 *     $request = Request::initial();
	 *
	 *     // Test whether the current request is the first request
	 *     if (Request::initial() === Request::current())
	 *          // Do something useful
	 *
	 * @since   3.1.0
	 *
	 * @return  static
	 */
	public static function initial()
	{
		return Request::$initial;
	}

	/**
	 * Returns information about the initial user agent.
	 *
	 * @uses    Request::$user_agent
	 * @uses    Text::user_agent
	 *
	 * @param   array|string   $value  array or string to return: browser, version, robot, mobile, platform
	 *
	 * @return  array<string, mixed>|string|false   requested information, FALSE if nothing is found
	 */
	public static function user_agent($value)
	{
		return Text::user_agent(Request::$user_agent, $value);
	}

	/**
	 * Determines if a file larger than the post_max_size has been uploaded. PHP
	 * does not handle this situation gracefully on its own, so this method
	 * helps to solve that problem.
	 *
	 * @uses    Num::bytes
	 * @uses    Arr::get
	 *
	 * @return  boolean
	 */
	public static function post_max_size_exceeded()
	{
		// Make sure the request method is POST
		if (Request::$initial->method() !== HTTP_Request::POST)
			return FALSE;

		// Get the post_max_size in bytes
		$max_bytes = Num::bytes(ini_get('post_max_size'));

		// Error occurred if method is POST, and content length is too long
		return (Arr::get($_SERVER, 'CONTENT_LENGTH') > $max_bytes);
	}

	/**
	 * Process a request to find a matching route
	 *
	 * @param   static  $request Request
	 * @param   array<string, Route>|null   $routes  Route
	 *
	 * @return  array{params: array<string, mixed>, route: Route}|null
	 */
	public static function process(Request $request, $routes = NULL)
	{
		// Load routes
		$routes = (empty($routes)) ? Route::all() : $routes;
		$params = NULL;

		foreach ($routes as $route)
		{
			// Use external routes for reverse routing only
			if ($route->is_external())
			{
				continue;
			}

			// We found something suitable
			if ($params = $route->matches($request))
			{
				return [
					'params' => $params,
					'route' => $route,
				];
			}
		}

		return NULL;
	}

	/**
	 * Parses an accept header and returns an array (type => quality) of the
	 * accepted types, ordered by quality.
	 *
	 *     $accept = Request::_parse_accept($header, $defaults);
	 *
	 * @param   string   $header   Header to parse
	 * @param   array<string, float>|null    $accepts  Default values
	 *
	 * @return  array<string, float>
	 */
	protected static function _parse_accept( & $header, array $accepts = NULL)
	{
		if ( ! empty($header))
		{
			// Get all of the types
			$types = explode(',', $header);

			foreach ($types as $type)
			{
				// Split the type into parts
				$parts = explode(';', $type);

				// Make the type only the MIME
				$type = trim(array_shift($parts));

				// Default quality is 1.0
				$quality = 1.0;

				foreach ($parts as $part)
				{
					// Prevent undefined $value notice below
					if (strpos($part, '=') === FALSE)
						continue;

					// Separate the key and value
					list ($key, $value) = explode('=', trim($part));

					if ($key === 'q')
					{
						// There is a quality for this type
						$quality = (float) trim($value);
					}
				}

				// Add the accept type and quality
				$accepts[$type] = $quality;
			}
		}

		// Make sure that accepts is an array
		$accepts = (array) $accepts;

		// Order by quality
		arsort($accepts);

		return $accepts;
	}

	/**
	 * @var  string|null  the x-requested-with header which most likely
	 *               will be xmlhttprequest
	 */
	protected $_requested_with;

	/**
	 * @var  string  method: GET, POST, PUT, DELETE, HEAD, etc
	 */
	protected $_method = 'GET';

	/**
	 * @var  string|null  protocol: HTTP/1.1, FTP, CLI, etc
	 */
	protected $_protocol;

	/**
	 * @var  boolean
	 */
	protected $_secure = FALSE;

	/**
	 * @var  string|null  referring URL
	 */
	protected $_referrer;

	/**
	 * @var  Route|null       route matched for this request
	 */
	protected $_route;

	/**
	 * @var  array<string, Route>       array of routes to manually look at instead of the global namespace
	 */
	protected $_routes;

	/**
	 * @var  HTTP_Header  headers to sent as part of the request
	 */
	protected $_header;

	/**
	 * @var  string|null the body
	 */
	protected $_body;

	/**
	 * @var  string  controller directory
	 */
	protected $_directory = '';

	/**
	 * @var  string|null  controller to be executed
	 */
	protected $_controller;

	/**
	 * @var string|null Requested Format (json, xml, html)
	 */
  protected $_format;

	/**
	 * @var  string|null  action to be executed in the controller
	 */
	protected $_action;

	/**
	 * @var  string  the URI of the request
	 */
	protected $_uri;

	/**
	 * @var  boolean  external request
	 */
	protected $_external = FALSE;

	/**
	 * @var  array<string, mixed>   parameters from the route
	 */
	protected $_params = [];

	/**
	 * @var array<string, mixed>   query parameters
	 */
	protected $_get = [];

	/**
	 * @var array<string, mixed>    post parameters
	 */
	protected $_post = [];

	/**
	 * @var array<string, string>    cookies to send with the request
	 */
	protected $_cookies = [];

	/**
	 * @var Request_Client
	 */
	protected $_client;

	/**
	 * Creates a new request object for the given URI. New requests should be
	 * Created using the [Request::factory] method.
	 *
	 *     $request = new Request($uri);
	 *
	 * If $cache parameter is set, the response for the request will attempt to
	 * be retrieved from the cache.
	 *
	 * @uses    Route::all
	 * @uses    Route::matches
	 *
	 * @param   string  $uri              URI of the request
	 * @param   array<string, mixed>   $client_params    Array of params to pass to the request client
	 * @param   bool    $allow_external   Allow external requests? (deprecated in 3.3)
	 * @param   array<string, Route>   $injected_routes  An array of routes to use, for testing
	 *
	 * @return  void
	 *
	 * @throws  Request_Exception
	 */
	public function __construct($uri, $client_params = [], $allow_external = TRUE, $injected_routes = [])
	{
		$client_params = is_array($client_params) ? $client_params : [];

		// Initialise the header
		$this->_header = new HTTP_Header([]);

		// Assign injected routes
		$this->_routes = $injected_routes;

		// Cleanse query parameters from URI (faster that parse_url())
		$split_uri = explode('?', $uri);
		$uri = array_shift($split_uri);

		if ($split_uri)
		{
			parse_str($split_uri[0], $this->_get);
		}

		// Detect protocol (if present)
		// $allow_external = FALSE prevents the default index.php from
		// being able to proxy external pages.
		if ( ! $allow_external OR (strpos($uri, '://') === FALSE AND strncmp($uri, '//', 2)))
		{
			// Remove leading and trailing slashes from the URI
			$this->_uri = trim($uri, '/');

			// Apply the client
			$this->_client = new Request_Client_Internal($client_params);
		}
		else
		{
			// Create a route
			$this->_route = new Route($uri);

			// Store the URI
			$this->_uri = $uri;

			// Set the security setting if required
			if (strpos($uri, 'https://') === 0)
			{
				$this->secure(TRUE);
			}

			// Set external state
			$this->_external = TRUE;

			// Setup the client
			$this->_client = Request_Client_External::factory($client_params);
		}
	}

	/**
	 * Returns the response as the string representation of a request.
	 *
	 *     echo $request;
	 *
	 * @return  string
	 */
	public function __toString()
	{
		return $this->render();
	}

	/**
	 * Sets and gets the uri from the request.
	 *
	 * @param   string $uri
	 *
	 * @return  static|string value of the uri as getter, $this as setter
	 */
	public function uri($uri = NULL)
	{
		if ($uri === NULL)
		{
			// Act as a getter
			return ($this->_uri === '') ? '/' : $this->_uri;
		}

		// Act as a setter
		$this->_uri = $uri;

		return $this;
	}

	/**
	 * Create a URL string from the current request. This is a shortcut for:
	 *
	 *     echo URL::site($this->request->uri(), $protocol);
	 *
	 * @since   3.0.7
	 *
	 * @uses    URL::site
	 *
	 * @param   Request|string    $protocol  protocol string or Request object
	 *
	 * @return  string
	 */
	public function url($protocol = NULL)
	{
		if ($this->is_external())
		{
			// If it's an external request return the URI
			return $this->uri();
		}

		// Create a URI with the current route, convert to a URL and returns
		return URL::site($this->uri(), $protocol);
	}

	/**
	 * Retrieves a value from the route parameters.
	 *
	 *     $id = $request->param('id');
	 *
	 * @template TValue
	 * @template TDefault
	 *
	 * @param   string           $key      Key of the value
	 * @param   TDefault|null    $default  Default value if the key is not set
	 *
	 * @return  array<string, TValue>|TValue|TDefault|null the entire params array if no key is set,
	 *                                                     otherwise the value or default if not found
	 */
	public function param($key = NULL, $default = NULL)
	{
		if ($key === NULL)
		{
			// Return the full array
			return $this->_params;
		}

		return isset($this->_params[$key]) ? $this->_params[$key] : $default;
	}

	/**
	 * Get / Set requested format
	 *
	 * @param string|null $format e.g JSON, XML, etc...
	 *
	 * @return  static|string value of the format as getter, $this as setter
	 */
	public function format(?string $format = NULL)
	{
		if ($format === NULL)
		{
			return $this->_format;
		}

		$this->_format = $format;

		return $this;
	}

	/**
	 * Sets and gets the referrer from the request.
	 *
	 * @param   string|null $referrer
	 *
	 * @return  static|string value of the referrer as getter, $this as setter
	 */
	public function referrer($referrer = NULL)
	{
		if ($referrer === NULL)
		{
			// Act as a getter
			return $this->_referrer;
		}

		// Act as a setter
		$this->_referrer = (string) $referrer;

		return $this;
	}

	/**
	 * Sets and gets the route from the request.
	 *
	 * @param   Route|null $route
	 *
	 * @return  static|Route|null value of the route as getter, $this as setter
	 */
	public function route(Route $route = NULL)
	{
		if ($route === NULL)
		{
			// Act as a getter
			return $this->_route;
		}

		// Act as a setter
		$this->_route = $route;

		return $this;
	}

	/**
	 * Sets and gets the directory for the controller.
	 *
	 * @param   string|null   $directory  Directory to execute the controller from
	 *
	 * @return  static|string value of the directory as getter, $this as setter
	 */
	public function directory($directory = NULL)
	{
		if ($directory === NULL)
		{
			// Act as a getter
			return $this->_directory;
		}

		// Act as a setter
		$this->_directory = (string) $directory;

		return $this;
	}

	/**
	 * Sets and gets the controller for the matched route.
	 *
	 * @param   string|null   $controller  Controller to execute the action
	 *
	 * @return  static|string|null value of the controller as getter, $this as setter
	 */
	public function controller($controller = NULL)
	{
		if ($controller === NULL)
		{
			// Act as a getter
			return $this->_controller;
		}

		// Act as a setter
		$this->_controller = (string) $controller;

		return $this;
	}

	/**
	 * Sets and gets the action for the controller.
	 *
	 * @param   string|null   $action  Action to execute the controller from
	 *
	 * @return  static|string|null value of the action as getter, $this as setter
	 */
	public function action($action = NULL)
	{
		if ($action === NULL)
		{
			// Act as a getter
			return $this->_action;
		}

		// Act as a setter
		$this->_action = (string) $action;

		return $this;
	}

	/**
	 * Provides access to the [Request_Client].
	 *
	 * @param  Request_Client|null $client
	 *
	 * @return  static|Request_Client the client as getter, $this as setter
	 */
	public function client(Request_Client $client = NULL)
	{
		if ($client === NULL)
			return $this->_client;
		else
		{
			$this->_client = $client;
			return $this;
		}
	}

	/**
	 * Gets and sets the requested with property, which should
	 * be relative to the x-requested-with pseudo header.
	 *
	 * @param   string|null    $requested_with Requested with value
	 *
	 * @return  static|string|null value of the requested with as getter, $this as setter
	 */
	public function requested_with($requested_with = NULL)
	{
		if ($requested_with === NULL)
		{
			// Act as a getter
			return $this->_requested_with;
		}

		// Act as a setter
		$this->_requested_with = strtolower($requested_with);

		return $this;
	}

	/**
	 * Processes the request, executing the controller action that handles this
	 * request, determined by the [Route].
	 *
	 * 1. Before the controller action is called, the [Controller::before] method
	 * will be called.
	 * 2. Next the controller action will be called.
	 * 3. After the controller action is called, the [Controller::after] method
	 * will be called.
	 *
	 * By default, the output from the controller is captured and returned, and
	 * no headers are sent.
	 *
	 *     $request->execute();
	 *
	 * @uses    [KO7::$profiling]
	 * @uses    [Profiler]
	 *
	 * @return  Response
	 *
	 * @throws  Request_Exception
	 * @throws  HTTP_Exception_404
	 */
	public function execute()
	{
		if ( ! $this->_external)
		{
			$processed = Request::process($this, $this->_routes);

			if ($processed)
			{
				// Store the matching route
				$this->_route = $processed['route'];
				$params = $processed['params'];

				// Is this route external?
				$this->_external = $this->_route->is_external();

				if (isset($params['directory']))
				{
					// Controllers are in a sub-directory
					$this->_directory = $params['directory'];
				}

				// Requested format e.g XML, JSON, etc..
				if (isset($params['format']))
				{
					$this->_format = $params['format'];
				}

				// Store the controller
				$this->_controller = $params['controller'];

				// Store the action
				$this->_action = (isset($params['action']))
					? $params['action']
					: Route::$default_action;

				// These are accessible as public vars and can be overloaded
				unset($params['controller'], $params['action'], $params['directory']);

				// Params cannot be changed once matched
				$this->_params = $params;
			}
		}

		if ( ! $this->_route instanceof Route)
		{
			return HTTP_Exception::factory(404, 'Unable to find a route to match the URI: :uri', [
				':uri' => $this->_uri,
			])->request($this)
				->get_response();
		}

		if ( ! $this->_client instanceof Request_Client)
		{
			throw new Request_Exception('Unable to execute :uri without a Request_Client', [
				':uri' => $this->_uri,
			]);
		}

		return $this->_client->execute($this);
	}

	/**
	 * Returns whether this request is the initial request KO7 received.
	 * Can be used to test for sub requests.
	 *
	 *     if ( ! $request->is_initial())
	 *         // This is a sub request
	 *
	 * @return  boolean
	 */
	public function is_initial()
	{
		return ($this === Request::$initial);
	}

	/**
	 * Readonly access to the [Request::$_external] property.
	 *
	 *     if ( ! $request->is_external())
	 *          // This is an internal request
	 *
	 * @return  boolean
	 */
	public function is_external()
	{
		return $this->_external;
	}

	/**
	 * Returns whether this is an ajax request (as used by JS frameworks)
	 *
	 * @return  boolean
	 */
	public function is_ajax()
	{
		return ($this->requested_with() === 'xmlhttprequest');
	}

	/**
	 * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
	 * traditional CRUD applications.
	 *
	 * @param   string|null   $method  Method to use for this request
	 *
	 * @return  static|string request method as getter ('GET', 'POST', 'PUT', 'DELETE', 'HEAD', etc)
	 *                        or $this as setter
	 */
	public function method($method = NULL)
	{
		if ($method === NULL)
		{
			// Act as a getter
			return $this->_method;
		}

		// Method is always uppercase
		$method = strtoupper($method);

		// Allow overriding method
		$override = $this->headers('X-HTTP-Method-Override');

		// Act as a setter
		$this->_method = $override && defined('HTTP_REQUEST::' . $override) ? $override : $method;

		return $this;
	}

	/**
	 * Gets or sets the HTTP protocol. If there is no current protocol set,
	 * it will use the default set in HTTP::$protocol
	 *
	 * @param   string|null   $protocol  Protocol to set to the request
	 *
	 * @return  static|string protocol value as getter, $this as setter
	 */
	public function protocol($protocol = NULL)
	{
		if ($protocol === NULL)
		{
			if ($this->_protocol)
				return $this->_protocol;
			else
				return $this->_protocol = HTTP::$protocol;
		}

		// Act as a setter
		$this->_protocol = strtoupper($protocol);
		return $this;
	}

	/**
	 * Getter/Setter to the security settings for this request. This
	 * method should be treated as immutable.
	 *
	 * @param   boolean|null $secure is this request secure?
	 *
	 * @return  static|bool secure value as getter, $this as setter
	 */
	public function secure($secure = NULL)
	{
		if ($secure === NULL)
			return $this->_secure;

		// Act as a setter
		$this->_secure = (bool) $secure;
		return $this;
	}

	/**
	 * Gets or sets HTTP headers oo the request. All headers
	 * are included immediately after the HTTP protocol definition during
	 * transmission. This method provides a simple array or key/value
	 * interface to the headers.
	 *
	 * @template TValue
	 *
	 * @param   HTTP_Header|array<string, mixed> $key   Key or array of key/value pairs to set
	 * @param   string|null                      $value Value to set to the supplied key
	 *
	 * @return  static|HTTP_Header|TValue|null all headers or single header as getter, $this as setter
	 */
	public function headers($key = NULL, $value = NULL)
	{
		if ($key instanceof HTTP_Header)
		{
			// Act a setter, replace all headers
			$this->_header = $key;

			return $this;
		}

		if (is_array($key))
		{
			// Act as a setter, replace all headers
			$this->_header->exchangeArray($key);

			return $this;
		}

		if ($this->_header->count() === 0 AND $this->is_initial())
		{
			// Lazy load the request headers
			$this->_header = HTTP::request_headers();
		}

		if ($key === NULL)
		{
			// Act as a getter, return all headers
			return $this->_header;
		}
		elseif ($value === NULL)
		{
			// Act as a getter, single header
			return ($this->_header->offsetExists($key)) ? $this->_header->offsetGet($key) : NULL;
		}

		// Act as a setter for a single header
		$this->_header[$key] = $value;

		return $this;
	}

	/**
	 * Set and get cookies values for this request.
	 *
	 * @param   string|array<string, string>|null $key    Cookie name, or array of cookie values
	 * @param   string|null                       $value  Value to set to cookie
	 *
	 * @return  static|array<string, string>|string|null all cookies or single cookie as getter, $this as setter
	 */
	public function cookie($key = NULL, $value = NULL)
	{
		if (is_array($key))
		{
			// Act as a setter, replace all cookies
			$this->_cookies = $key;
			return $this;
		}
		elseif ($key === NULL)
		{
			// Act as a getter, all cookies
			return $this->_cookies;
		}
		elseif ($value === NULL)
		{
			// Act as a getting, single cookie
			return isset($this->_cookies[$key]) ? $this->_cookies[$key] : NULL;
		}

		// Act as a setter for a single cookie
		$this->_cookies[$key] = (string) $value;

		return $this;
	}

	/**
	 * Gets or sets the HTTP body of the request. The body is
	 * included after the header, separated by a single empty new line.
	 *
	 * @param   string|null  $content Content to set to the object
	 *
	 * @return  static|string|null body value as getter, $this as setter
	 */
	public function body($content = NULL)
	{
		if ($content === NULL)
		{
			// Act as a getter
			return $this->_body;
		}

		// Act as a setter
		$this->_body = $content;

		return $this;
	}

	/**
	 * Returns the length of the body for use with
	 * content header
	 *
	 * @return  integer
	 */
	public function content_length()
	{
		return strlen($this->body());
	}

	/**
	 * Renders the HTTP_Interaction to a string, producing
	 *
	 *  - Protocol
	 *  - Headers
	 *  - Body
	 *
	 *  If there are variables set to the `Request::$_post`
	 *  they will override any values set to body.
	 *
	 * @return  string
	 */
	public function render()
	{
		if ( ! $post = $this->post())
		{
			$body = $this->body();
		}
		else
		{
			$body = http_build_query($post, '', '&');
			$this->body($body)
				->headers('content-type', 'application/x-www-form-urlencoded; charset='.KO7::$charset);
		}

		// Set the content length
		$this->headers('content-length', (string) $this->content_length());

		// If KO7 expose, set the user-agent
		if (KO7::$expose)
		{
			$this->headers('user-agent', KO7::version());
		}

		// Prepare cookies
		if ($this->_cookies)
		{
			$cookie_string = [];

			// Parse each
			foreach ($this->_cookies as $key => $value)
			{
				$cookie_string[] = $key.'='.$value;
			}

			// Create the cookie string
			$this->_header['cookie'] = implode('; ', $cookie_string);
		}

		$output = $this->method().' '.$this->uri().' '.$this->protocol()."\r\n";
		$output .= (string) $this->_header;
		$output .= $body;

		return $output;
	}

	/**
	 * Gets or sets HTTP query string.
	 *
	 * @uses    Arr::path
	 *
	 * @param   string|array<string, mixed>|null $key    Key or key value pairs to set
	 * @param   string|null                      $value  Value to set to a key
	 *
	 * @return  static|array<string, mixed>|mixed|null all query values or single query value as getter, $this as setter
	 */
	public function query($key = NULL, $value = NULL)
	{
		if (is_array($key))
		{
			// Act as a setter, replace all query strings
			$this->_get = $key;

			return $this;
		}

		if ($key === NULL)
		{
			// Act as a getter, all query strings
			return $this->_get;
		}
		elseif ($value === NULL)
		{
			// Act as a getter, single query string
			return Arr::path($this->_get, $key);
		}

		// Act as a setter, single query string
		$this->_get[$key] = $value;

		return $this;
	}

	/**
	 * Gets or sets HTTP POST parameters to the request.
	 *
	 * @uses    Arr::path
	 *
	 * @param   string|array<string, mixed>|null $key    Key or key value pairs to set
	 * @param   string|null                      $value  Value to set to a key
	 *
	 * @return  static|array<string, mixed>|mixed|null all post values or single post value as getter, $this as setter
	 */
	public function post($key = NULL, $value = NULL)
	{
		if (is_array($key))
		{
			// Act as a setter, replace all fields
			$this->_post = $key;

			return $this;
		}

		if ($key === NULL)
		{
			// Act as a getter, all fields
			return $this->_post;
		}
		elseif ($value === NULL)
		{
			// Act as a getter, single field
			return Arr::path($this->_post, $key);
		}

		// Act as a setter, single field
		$this->_post[$key] = $value;

		return $this;
	}

}
