<?php

namespace PrettyPrinter\Reflection;

use PrettyPrinter\TypeHandler;
use PrettyPrinter\Utils\Text;

class Variable
{
	private $name, $value;

	function __construct( $name, &$value )
	{
		$this->name  = $name;
		$this->value =& $value;
	}

	function &value()
	{
		return $this->value;
	}

	function name()
	{
		return $this->name;
	}

	function prefix()
	{
		return new Text;
	}

	function prettyPrint( TypeHandler $any )
	{
		return $this->prefix()->appendLines( $any->prettyPrintVariable( $this->name ) );
	}
}