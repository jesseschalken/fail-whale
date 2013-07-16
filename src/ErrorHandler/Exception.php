<?php

namespace ErrorHandler;

use PrettyPrinter\HasStackTraceWithCurrentObjects;

/**
 * Same as \Exception except it includes a full stack trace
 *
 * @package ErrorHandler
 */
class Exception extends \Exception implements HasStackTraceWithCurrentObjects
{
	private $fullStackTrace;

	function __construct( $message = "", $code = 0, \Exception $previous = null )
	{
		$this->fullStackTrace = array_slice( debug_backtrace(), 1 );

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * @return array
	 */
	function getStackTraceWithCurrentObjects()
	{
		return $this->fullStackTrace;
	}
}