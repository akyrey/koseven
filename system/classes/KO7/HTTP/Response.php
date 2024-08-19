<?php
/**
 * A HTTP Response specific interface that adds the methods required
 * by HTTP responses. Over and above [KO7_HTTP_Interaction], this
 * interface provides status.
 *
 * @package    KO7
 * @category   HTTP
 *
 * @since      3.1.0
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
interface KO7_HTTP_Response extends HTTP_Message {

	/**
	 * Sets or gets the HTTP status from this response.
	 *
	 *      // Set the HTTP status to 404 Not Found
	 *      $response = Response::factory()
	 *              ->status(404);
	 *
	 *      // Get the current status
	 *      $status = $response->status();
	 *
	 * @param   integer|null  $code  Status to set to this response
   *
	 * @return  mixed
	 */
	public function status($code = NULL);

}
