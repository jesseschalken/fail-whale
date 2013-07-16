<?php

namespace ErrorHandler;

use PrettyPrinter\HasStackTraceWithCurrentObjects;

class AssertionFailedException extends \Exception implements HasStackTraceWithCurrentObjects
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

	function getStackTraceWithCurrentObjects()
	{
		return $this->fullStackTrace;
	}
}

