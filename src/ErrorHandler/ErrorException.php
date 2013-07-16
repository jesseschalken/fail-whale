<?php

namespace ErrorHandler;

use PrettyPrinter\Utils\ArrayUtil;
use PrettyPrinter\HasStackTraceWithCurrentObjects;
use PrettyPrinter\HasLocalVariables;

class ErrorException extends \ErrorException implements HasLocalVariables, HasStackTraceWithCurrentObjects
{
	private static $errorConstants = array( E_ERROR             => 'E_ERROR',
	                                        E_WARNING           => 'E_WARNING',
	                                        E_PARSE             => 'E_PARSE',
	                                        E_NOTICE            => 'E_NOTICE',
	                                        E_CORE_ERROR        => 'E_CORE_ERROR',
	                                        E_CORE_WARNING      => 'E_CORE_WARNING',
	                                        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
	                                        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
	                                        E_USER_ERROR        => 'E_USER_ERROR',
	                                        E_USER_WARNING      => 'E_USER_WARNING',
	                                        E_USER_NOTICE       => 'E_USER_NOTICE',
	                                        E_STRICT            => 'E_STRICT',
	                                        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
	                                        E_DEPRECATED        => 'E_DEPRECATED',
	                                        E_USER_DEPRECATED   => 'E_USER_DEPRECATED' );

	private $localVariables, $fullStackTrace;

	/**
	 * @param int         $severity
	 * @param string      $message
	 * @param string|null $file
	 * @param int|null    $line
	 * @param array|null  $localVariables
	 * @param array|null  $fullStackTrace
	 */
	function __construct( $severity, $message, $file, $line, array $localVariables = null,
	                      array $fullStackTrace = null )
	{
		parent::__construct( $message, 0, $severity, $file, $line );

		$this->localVariables = $localVariables;
		$this->fullStackTrace = $fullStackTrace;
		$this->code           = ArrayUtil::get( self::$errorConstants, $severity, 'E_?' );
	}

	function getStackTraceWithCurrentObjects()
	{
		return isset( $this->fullStackTrace ) ? $this->fullStackTrace : $this->getTrace();
	}

	function getLocalVariables()
	{
		return $this->localVariables;
	}
}
