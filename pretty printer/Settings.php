<?php

final class PrettyPrinterSettings
{
	private $escapeTabsInStrings = false;
	private $splitMultiLineStrings = true;
	private $renderArraysMultiLine = true;
	private $maxObjectProperties = PHP_INT_MAX;
	private $maxArrayEntries = PHP_INT_MAX;
	private $maxStringLength = PHP_INT_MAX;
	private $showExceptionLocalVariables = true;
	private $showExceptionGlobalVariables = true;

	public function escapeTabsInStrings()
	{
		return new PrettyPrinterSettingYesNo( $this->escapeTabsInStrings );
	}

	public function splitMultiLineStrings()
	{
		return new PrettyPrinterSettingYesNo( $this->splitMultiLineStrings );
	}

	public function renderArraysMultiLine()
	{
		return new PrettyPrinterSettingYesNo( $this->renderArraysMultiLine );
	}

	public function maxStringLength()
	{
		return new PrettyPrinterSettingInt( $this->maxStringLength );
	}

	public function maxArrayEntries()
	{
		return new PrettyPrinterSettingInt( $this->maxArrayEntries );
	}

	public function maxObjectProperties()
	{
		return new PrettyPrinterSettingInt( $this->maxObjectProperties );
	}

	public function showExceptionLocalVariables()
	{
		return new PrettyPrinterSettingYesNo( $this->showExceptionLocalVariables );
	}

	public function showExceptionGlobalVariables()
	{
		return new PrettyPrinterSettingYesNo( $this->showExceptionGlobalVariables );
	}

	private function valuePrettyPrinter()
	{
		return new ValuePrettyPrinter( $this );
	}

	public function prettyPrint( $value )
	{
		return $this->prettyPrintRef( $value );
	}

	public function prettyPrintRef( &$ref )
	{
		return $this->valuePrettyPrinter()->doPrettyPrint( $ref )->join();
	}

	public function prettyPrintException( Exception $e )
	{
		return $this->valuePrettyPrinter()->prettyPrintException( $e )->join();
	}

	public function prettyPrintVariables( array $variables )
	{
		return $this->valuePrettyPrinter()->prettyPrintVariables( $variables )->join();
	}
}

abstract class PrettyPrinterSetting
{
	private $ref;

	public function __construct( &$ref )
	{
		$this->ref =& $ref;
	}

	public function set( $value )
	{
		$this->ref = $value;
	}

	public function get()
	{
		return $this->ref;
	}
}

final class PrettyPrinterSettingYesNo extends PrettyPrinterSetting
{
	public function yes()
	{
		$this->set( true );
	}

	public function no()
	{
		$this->set( false );
	}

	public function set( $value )
	{
		parent::set( (bool) $value );
	}

	public function isYes()
	{
		return $this->get() === true;
	}

	public function isNo()
	{
		return $this->get() === false;
	}

	public function ifElse( $true, $false )
	{
		return $this->get() ? $true : $false;
	}
}

final class PrettyPrinterSettingInt extends PrettyPrinterSetting
{
	public function set( $value )
	{
		parent::set( (int) $value );
	}

	public function infinity()
	{
		parent::set( PHP_INT_MAX );
	}
}
