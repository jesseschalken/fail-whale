<?php

namespace PrettyPrinter\Reflection
{
	use PrettyPrinter\Utils\Text;
	use PrettyPrinter\Memory;

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

	class GlobalVariable extends Variable
	{
		function isSuperGlobal()
		{
			return in_array( $this->name(), array( '_POST',
			                                       '_GET',
			                                       '_SESSION',
			                                       '_COOKIE',
			                                       '_FILES',
			                                       '_REQUEST',
			                                       '_ENV',
			                                       '_SERVER' ), true );
		}

		function prefix()
		{
			return new Text( $this->isSuperGlobal() ? '' : 'global ' );
		}
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

		function prefix()
		{
			return new Text( "function $this->class::$this->method()::static " );
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

		function prefix()
		{
			return new Text;
		}

		function prettyPrint( Memory $any )
		{
			return $this->prefix()->appendLines( $any->prettyPrintVariable( $this->name ) );
		}
	}
}

