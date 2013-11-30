<?php

namespace PrettyPrinter\Reflection
{
	use PrettyPrinter\Utils\Text;

	class MethodStaticVariable extends Variable
	{
		private $class, $method;

		function __construct( $class, $method, $name, &$value )
		{
			$this->class  = $class;
			$this->method = $method;

			parent::__construct( $name, $value );
		}

		function prefix()
		{
			return new Text( "function $this->class::$this->method()::static " );
		}
	}
}