<?php

namespace PrettyPrinter;

use PrettyPrinter\Settings\Bool;
use PrettyPrinter\Settings\Number;
use PrettyPrinter\TypeHandlers\Any;
use PrettyPrinter\TypeHandlers\Exception;

final class PrettyPrinter
{
	private $escapeTabsInStrings = false;
	private $splitMultiLineStrings = true;
	private $renderArraysMultiLine = true;
	private $maxObjectProperties = INF;
	private $maxArrayEntries = INF;
	private $maxStringLength = INF;
	private $showExceptionLocalVariables = true;
	private $showExceptionGlobalVariables = true;
	private $showExceptionStackTrace = true;

	function escapeTabsInStrings() { return new Bool( $this->escapeTabsInStrings ); }

	function splitMultiLineStrings() { return new Bool( $this->splitMultiLineStrings ); }

	function renderArraysMultiLine() { return new Bool( $this->renderArraysMultiLine ); }

	function maxObjectProperties() { return new Bool( $this->maxObjectProperties ); }

	function maxArrayEntries() { return new Number( $this->maxArrayEntries ); }

	function maxStringLength() { return new Number( $this->maxStringLength ); }

	function showExceptionLocalVariables() { return new Bool( $this->showExceptionLocalVariables ); }

	function showExceptionGlobalVariables() { return new Bool( $this->showExceptionGlobalVariables ); }

	function showExceptionStackTrace() { return new Bool( $this->showExceptionStackTrace ); }

	function prettyPrint( $value )
	{
		return $this->prettyPrintRef( $value );
	}

	function prettyPrintRef( &$ref )
	{
		$anyHandler = new Any( $this );

		return $anyHandler->handleValue( $ref )->__toString();
	}

	function prettyPrintException( \Exception $e )
	{
		$exceptionHandler = new Exception( new Any( $this ) );

		return $exceptionHandler->handleValue( $e )->__toString();
	}
}
