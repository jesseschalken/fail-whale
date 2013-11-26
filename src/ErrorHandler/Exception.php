<?php

namespace ErrorHandler;

use PrettyPrinter\ExceptionExceptionInfo;
use PrettyPrinter\HasExceptionInfo;
use PrettyPrinter\Utils\ArrayUtil;

/**
 * Same as \Exception except it includes a full stack trace
 *
 * @package ErrorHandler
 */
class Exception extends \Exception implements HasExceptionInfo
{
	/**
	 * @param array  $stackTrace
	 * @param object $lastObject
	 *
	 * @return array
	 */
	private static function pruneConstructors( array $stackTrace, $lastObject )
	{
		$i = 0;

		foreach ( $stackTrace as $stackFrame )
		{
			if ( ArrayUtil::get( $stackFrame, 'object' ) !== $lastObject )
				break;

			$i++;
		}

		return array_slice( $stackTrace, $i );
	}

	private $stackTrace;

	function __construct( $message = "", $code = 0, \Exception $previous = null )
	{
		$this->stackTrace = self::pruneConstructors( debug_backtrace(), $this );

		parent::__construct( $message, $code, $previous );
	}

	function info()
	{
		return new ExceptionExceptionInfo( $this, null, $this->stackTrace );
	}
}
