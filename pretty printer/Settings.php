<?php

final class PrettyPrinterSettings
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

	function escapeTabsInStrings()
	{
		return new PrettyPrinterSettingYesNo( $this->escapeTabsInStrings );
	}

	function splitMultiLineStrings()
	{
		return new PrettyPrinterSettingYesNo( $this->splitMultiLineStrings );
	}

	function renderArraysMultiLine()
	{
		return new PrettyPrinterSettingYesNo( $this->renderArraysMultiLine );
	}

	function maxStringLength()
	{
		return new PrettyPrinterSettingNumber( $this->maxStringLength );
	}

	function maxArrayEntries()
	{
		return new PrettyPrinterSettingNumber( $this->maxArrayEntries );
	}

	function maxObjectProperties()
	{
		return new PrettyPrinterSettingNumber( $this->maxObjectProperties );
	}

	function showExceptionLocalVariables()
	{
		return new PrettyPrinterSettingYesNo( $this->showExceptionLocalVariables );
	}

	function showExceptionGlobalVariables()
	{
		return new PrettyPrinterSettingYesNo( $this->showExceptionGlobalVariables );
	}

	function showExceptionStackTrace()
	{
		return new PrettyPrinterSettingYesNo( $this->showExceptionStackTrace );
	}

	private function valuePrettyPrinter()
	{
		return new ValuePrettyPrinter( $this );
	}

	function prettyPrint( $value )
	{
		return $this->prettyPrintRef( $value );
	}

	function prettyPrintRef( &$ref )
	{
		return $this->valuePrettyPrinter()->doPrettyPrint( $ref )->join();
	}

	function prettyPrintException( Exception $e )
	{
		return $this->valuePrettyPrinter()->prettyPrintException( $e )->join();
	}

	function prettyPrintVariables( array $variables )
	{
		return $this->valuePrettyPrinter()->prettyPrintVariables( $variables )->join();
	}
}

abstract class PrettyPrinterSetting
{
	private $ref;

	function __construct( &$ref )
	{
		$this->ref =& $ref;
	}

	function set( $value )
	{
		$this->ref = $value;
	}

	function get()
	{
		return $this->ref;
	}
}

final class PrettyPrinterSettingYesNo extends PrettyPrinterSetting
{
	function yes()
	{
		$this->set( true );
	}

	function no()
	{
		$this->set( false );
	}

	function set( $value )
	{
		parent::set( (bool) $value );
	}

	function isYes()
	{
		return $this->get() === true;
	}

	function isNo()
	{
		return $this->get() === false;
	}

	function ifElse( $true, $false )
	{
		return $this->get() ? $true : $false;
	}
}

final class PrettyPrinterSettingNumber extends PrettyPrinterSetting
{
	function set( $value )
	{
		parent::set( (float) $value );
	}

	public function infinity()
	{
		parent::set( INF );
	}
}
