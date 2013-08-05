<?php

namespace PrettyPrinter\Reflection;

use PrettyPrinter\Utils\Text;

class MethodStaticVariable extends Variable
{
	private $class, $method, $access;

	function __construct( $class, $method, $access, $name, &$value )
	{
		$this->class  = $class;
		$this->method = $method;
		$this->access = $access;

		parent::__construct( $name, $value );
	}

	function prefix()
	{
		return new Text( "$this->access function $this->class::$this->method()::static " );
	}
}