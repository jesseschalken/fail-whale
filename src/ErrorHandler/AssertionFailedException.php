<?php

namespace ErrorHandler;

use PrettyPrinter\ExceptionExceptionInfo;
use PrettyPrinter\HasExceptionInfo;

class AssertionFailedException extends \Exception implements HasExceptionInfo
{
	private $expression, $fullStackTrace;

	/**
	 * @param string $file
	 * @param int    $line
	 * @param string $expression
	 * @param string $message
	 * @param array  $fullStackTrace
	 */
	function __construct( $file, $line, $expression, $message, array $fullStackTrace )
	{
		$this->file           = $file;
		$this->line           = $line;
		$this->expression     = $expression;
		$this->message        = $message;
		$this->fullStackTrace = $fullStackTrace;
	}

	function info()
	{
		return new ExceptionExceptionInfo( $this, null, $this->fullStackTrace );
	}
}

