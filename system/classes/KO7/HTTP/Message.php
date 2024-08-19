<?php
/**
 * The HTTP Interaction interface providing the core HTTP methods that
 * should be implemented by any HTTP request or response class.
 *
 * @package    KO7
 * @category   HTTP
 *
 * @since      3.1.0
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
interface KO7_HTTP_Message {

	/**
	 * Gets or sets the HTTP protocol. The standard protocol to use
	 * is `HTTP/1.1`.
	 *
	 * @param   string|null   $protocol  Protocol to set to the request/response
   *
	 * @return  mixed
	 */
	public function protocol($protocol = NULL);

	/**
	 * Gets or sets HTTP headers to the request or response. All headers
	 * are included immediately after the HTTP protocol definition during
	 * transmission. This method provides a simple array or key/value
	 * interface to the headers.
	 *
	 * @param   string|string[]|null   $key    Key or array of key/value pairs to set
	 * @param   string|null  $value  Value to set to the supplied key
   *
	 * @return  mixed
	 */
	public function headers($key = NULL, $value = NULL);

	/**
	 * Gets or sets the HTTP body to the request or response. The body is
	 * included after the header, separated by a single empty new line.
	 *
	 * @param   mixed    $content  Content to set to the object
   *
	 * @return  string
	 */
	public function body($content = NULL);

	/**
	 * Renders the HTTP_Interaction to a string, producing
	 *
	 *  - Protocol
	 *  - Headers
	 *  - Body
	 *
	 * @return  string
	 */
	public function render();

}
