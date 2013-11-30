<?php

namespace PrettyPrinter
{
	use PrettyPrinter\Reflection\ClassStaticProperty;
	use PrettyPrinter\Reflection\FunctionStaticVariable;
	use PrettyPrinter\Reflection\GlobalVariable;
	use PrettyPrinter\Reflection\MethodStaticVariable;
	use PrettyPrinter\Reflection\Variable;
	use PrettyPrinter\Utils\Ref;

	abstract class ExceptionInfo
	{
		static function fromException( \Exception $e )
		{
			$localVariables = $e instanceof HasLocalVariables ? $e->getLocalVariables() : null;
			$stackTrace     = $e instanceof HasFullTrace ? $e->getFullTrace() : null;

			return new ExceptionExceptionInfo( $e, $localVariables, $stackTrace );
		}

		abstract function exceptionClassName();

		abstract function message();

		abstract function code();

		abstract function file();

		abstract function line();

		abstract function previous();

		abstract function localVariables();

		abstract function stackTrace();

		/**
		 * @return Variable[]
		 */
		function globalVariables()
		{
			$globals = array();

			foreach ( $GLOBALS as $name => &$value )
				if ( $name !== 'GLOBALS' )
					$globals[ ] = new GlobalVariable( $name, $value );

			foreach ( get_declared_classes() as $class )
			{
				$reflection = new \ReflectionClass( $class );

				foreach ( $reflection->getProperties( \ReflectionProperty::IS_STATIC ) as $property )
				{
					$property->setAccessible( true );

					$globals[ ] = new ClassStaticProperty( $property->class,
					                                       Types\Exception::propertyOrMethodAccess( $property ),
					                                       $property->name, Ref::create( $property->getValue() ) );
				}

				foreach ( $reflection->getMethods() as $method )
				{
					foreach ( $method->getStaticVariables() as $name => $value )
					{
						$globals[ ] = new MethodStaticVariable( $method->class, $method->getName(), $name, $value );
					}
				}
			}

			foreach ( get_defined_functions() as $section )
			{
				foreach ( $section as $function )
				{
					$reflection = new \ReflectionFunction( $function );

					foreach ( $reflection->getStaticVariables() as $name => $value )
						$globals[ ] = new FunctionStaticVariable( $reflection->name, $name, $value );
				}
			}

			return $globals;
		}
	}

	interface HasFullTrace
	{
		function getFullTrace();
	}

	interface HasLocalVariables
	{
		function getLocalVariables();
	}

	class ExceptionExceptionInfo extends ExceptionInfo
	{
		private $e, $localVariables, $stackTrace;

		function __construct( \Exception $e, array $localVariables = null, array $stackTrace )
		{
			$this->e              = $e;
			$this->localVariables = $localVariables;
			$this->stackTrace     = $stackTrace;
		}

		function message() { return $this->e->getMessage(); }

		function code() { return $this->e->getCode(); }

		function file() { return $this->e->getFile(); }

		function line() { return $this->e->getLine(); }

		function previous()
		{
			$previous = $this->e->getPrevious();

			return isset( $previous ) ? ExceptionInfo::fromException( $previous ) : null;
		}

		function localVariables() { return $this->localVariables; }

		function stackTrace() { return $this->stackTrace; }

		function exceptionClassName() { return get_class( $this->e ); }
	}
}