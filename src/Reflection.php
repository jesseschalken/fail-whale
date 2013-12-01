<?php

namespace PrettyPrinter\Reflection
{
	class ClassStaticProperty extends Variable
	{
		private $class, $access;

		function __construct( $class, $access, $name, &$value )
		{
			$this->class  = $class;
			$this->access = $access;

			parent::__construct( $name, $value );
		}

		function access()
		{
			return $this->access;
		}

		function className()
		{
			return $this->class;
		}
	}

	class FunctionStaticVariable extends Variable
	{
		private $function;

		function __construct( $function, $name, &$value )
		{
			$this->function = $function;

			parent::__construct( $name, $value );
		}

		function functionName()
		{
			return $this->function;
		}
	}

	class GlobalVariable extends Variable
	{
	}

	class MethodStaticVariable extends Variable
	{
		private $class, $method;

		function __construct( $class, $method, $name, &$value )
		{
			$this->class  = $class;
			$this->method = $method;

			parent::__construct( $name, $value );
		}

		function functionName()
		{
			return $this->method;
		}

		function className()
		{
			return $this->class;
		}
	}

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

		function className() { return null; }

		function functionName() { return null; }

		function access() { return null; }
	}
}

