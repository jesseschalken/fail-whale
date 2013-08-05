<?php

namespace ErrorHandler;

use PrettyPrinter\ExceptionExceptionInfo;
use PrettyPrinter\HasExceptionInfo;

/**
 * Same as \Exception except it includes a full stack trace
 *
 * @package ErrorHandler
 */
class Exception extends \Exception implements HasExceptionInfo
{
	private $stackTrace;

	function __construct( $message = "", $code = 0, \Exception $previous = null )
	{
		$this->stackTrace = array_slice( debug_backtrace(), 1 );

		parent::__construct( $message, $code, $previous );
	}

	function info()
	{
		return new ExceptionExceptionInfo( $this, null, $this->stackTrace );
	}
}