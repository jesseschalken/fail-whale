<?php

namespace PrettyPrinter;

class ExceptionExceptionInfo extends ExceptionInfo
{
	private $e, $localVariables, $stackTrace;

	function __construct( \Exception $e, array $localVariables = null, array $stackTrace = null )
	{
		$this->e              = $e;
		$this->localVariables = $localVariables;
		$this->stackTrace     = $stackTrace;
	}

	function message() { return $this->e->getMessage(); }

	function code() { return $this->e->getCode(); }

	function file() { return $this->e->getFile(); }

	function line() { return $this->e->getLine(); }

	function previous()
	{
		$previous = $this->e->getPrevious();

		return isset( $previous ) ? new self( $previous ) : null;
	}

	function localVariables() { return $this->localVariables; }

	function stackTrace() { return isset( $this->stackTrace ) ? $this->stackTrace : $this->e->getTrace(); }

	function exceptionClassName()
	{
		return get_class( $this->e );
	}
}