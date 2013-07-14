<?php

namespace PrettyPrinter;

use PrettyPrinter\TypeHandlers\Any;
use PrettyPrinter\Text;

abstract class TypeHandler
{
	/** @var Any */
	private $anyHandler;

	function __construct( Any $handler )
	{
		$this->anyHandler = $handler;
	}

	/**
	 * @param $value
	 *
	 * @return Text
	 */
	abstract function handleValue( &$value );

	protected final function prettyPrintRef( &$value )
	{
		return $this->anyHandler->handleValue( $value );
	}

	protected final function prettyPrint( $value )
	{
		return $this->anyHandler->handleValue( $value );
	}

	protected function prettyPrintVariable( $varName )
	{
		return $this->anyHandler->prettyPrintVariable( $varName );
	}

	protected function settings()
	{
		return $this->anyHandler->settings();
	}

	protected function newId()
	{
		return $this->anyHandler->newId();
	}
}

