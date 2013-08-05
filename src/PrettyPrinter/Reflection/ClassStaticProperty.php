<?php

namespace PrettyPrinter\Reflection;

use PrettyPrinter\Utils\Text;

class ClassStaticProperty extends Variable
{
	private $class, $access;

	function __construct( $class, $access, $name, &$value )
	{
		$this->class  = $class;
		$this->access = $access;

		parent::__construct( $name, $value );
	}

	function prefix()
	{
		return new Text( "$this->access static $this->class::" );
	}
}