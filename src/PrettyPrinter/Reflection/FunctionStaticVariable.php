<?php

namespace PrettyPrinter\Reflection;

use PrettyPrinter\Utils\Text;

class FunctionStaticVariable extends Variable
{
	private $function;

	function __construct( $function, $name, &$value )
	{
		$this->function = $function;

		parent::__construct( $name, $value );
	}

	function prefix()
	{
		return new Text( "function $this->function()::static " );
	}
}