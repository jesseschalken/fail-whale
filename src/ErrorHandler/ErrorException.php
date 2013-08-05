<?php

namespace ErrorHandler;

use PrettyPrinter\ExceptionExceptionInfo;
use PrettyPrinter\HasExceptionInfo;
use PrettyPrinter\Utils\ArrayUtil;

class ErrorException extends \ErrorException implements HasExceptionInfo
{
	private $localVariables, $stackTrace;

	function __construct( $severity, $message, $file, $line, array $localVariables = null, array $stackTrace )
	{
		parent::__construct( $message, 0, $severity, $file, $line );

		$this->localVariables = $localVariables;
		$this->stackTrace     = $stackTrace;
		$this->code           = ArrayUtil::get( array( E_ERROR             => 'E_ERROR',
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
		                                               E_USER_DEPRECATED   => 'E_USER_DEPRECATED' ), $severity, 'E_?' );
	}

	function info()
	{
		return new ExceptionExceptionInfo( $this, $this->localVariables, $this->stackTrace );
	}
}
