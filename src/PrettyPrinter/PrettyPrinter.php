<?php

namespace PrettyPrinter;

use PrettyPrinter\TypeHandlers\Any;
use PrettyPrinter\TypeHandlers\Exception;

/**
 * This is just a dumb data structure. Don't make any assumptions about the values when referencing this class's
 * fields.
 */
final class PrettyPrinter
{
	var $escapeTabsInStrings = false;
	var $splitMultiLineStrings = true;
	var $renderArraysMultiLine = true;
	var $maxObjectProperties = INF;
	var $maxArrayEntries = INF;
	var $maxStringLength = INF;
	var $showExceptionLocalVariables = true;
	var $showExceptionGlobalVariables = true;
	var $showExceptionStackTrace = true;

	function prettyPrint( $value )
	{
		return $this->prettyPrintRef( $value );
	}

	function prettyPrintRef( &$ref )
	{
		return $this->pp()->handleValue( $ref )->join();
	}

	function prettyPrintException( \Exception $e )
	{
		$exceptionHandler = new Exception( $this->pp() );

		return $exceptionHandler->handleValue( $e )->join();
	}

	private function pp()
	{
		return new Any( $this );
	}
}
