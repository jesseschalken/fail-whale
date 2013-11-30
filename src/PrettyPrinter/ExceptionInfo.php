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
			return $e instanceof HasExceptionInfo ? $e->info() : new ExceptionExceptionInfo( $e );
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
					                                       TypeHandlers\Exception::propertyOrMethodAccess( $property ),
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
}