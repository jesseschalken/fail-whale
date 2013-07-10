<?php

/**
 * This is just a dumb data structure. Don't make any assumptions about the values when referencing this class's
 * fields.
 */
final class PrettyPrinterSettings
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
		return $this->pp()->doPrettyPrint( $ref )->join();
	}

	function prettyPrintException( Exception $e )
	{
		return $this->pp()->prettyPrintException( $e )->join();
	}

	function prettyPrintVariables( array $variables )
	{
		return $this->pp()->prettyPrintVariables( $variables )->join();
	}

	private function pp()
	{
		return new ValuePrettyPrinter( $this );
	}
}
