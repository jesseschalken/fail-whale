<?php

namespace PrettyPrinter
{
	use PrettyPrinter\Settings\Bool;
	use PrettyPrinter\Settings\Number;
	use PrettyPrinter\TypeHandlers\Any;
	use PrettyPrinter\TypeHandlers\Exception as ExceptionHandler;

	final class PrettyPrinter
	{
		static function create() { return new self; }

		private $escapeTabsInStrings = false;
		private $splitMultiLineStrings = true;
		private $maxObjectProperties = INF;
		private $maxArrayEntries = INF;
		private $maxStringLength = INF;
		private $showExceptionLocalVariables = true;
		private $showExceptionGlobalVariables = true;
		private $showExceptionStackTrace = true;

		function escapeTabsInStrings() { return new Bool( $this, $this->escapeTabsInStrings ); }

		function splitMultiLineStrings() { return new Bool( $this, $this->splitMultiLineStrings ); }

		function maxObjectProperties() { return new Number( $this, $this->maxObjectProperties ); }

		function maxArrayEntries() { return new Number( $this, $this->maxArrayEntries ); }

		function maxStringLength() { return new Number( $this, $this->maxStringLength ); }

		function showExceptionLocalVariables() { return new Bool( $this, $this->showExceptionLocalVariables ); }

		function showExceptionGlobalVariables() { return new Bool( $this, $this->showExceptionGlobalVariables ); }

		function showExceptionStackTrace() { return new Bool( $this, $this->showExceptionStackTrace ); }

		function prettyPrint( $value )
		{
			return $this->prettyPrintRef( $value );
		}

		function prettyPrintRef( &$ref )
		{
			$anyHandler = new Any( $this );

			return $anyHandler->handleValue( $ref )->setHasEndingNewline( false )->__toString();
		}

		function prettyPrintExceptionInfo( ExceptionInfo $e )
		{
			$exceptionHandler = new ExceptionHandler( new Any( $this ) );

			return $exceptionHandler->handleValue( $e )->__toString();
		}

		function prettyPrintException( \Exception $e )
		{
			return $this->prettyPrintExceptionInfo( ExceptionInfo::fromException( $e ) );
		}

		function assertPrettyIs( $value, $expectedPretty )
		{
			\PHPUnit_Framework_TestCase::assertEquals( $expectedPretty, $this->prettyPrint( $value ) );

			return $this;
		}

		function assertPrettyRefIs( &$ref, $expectedPretty )
		{
			\PHPUnit_Framework_TestCase::assertEquals( $expectedPretty, $this->prettyPrintRef( $ref ) );

			return $this;
		}
	}
}