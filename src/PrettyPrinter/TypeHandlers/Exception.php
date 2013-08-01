<?php

namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\Utils\ArrayUtil;
use PrettyPrinter\HasStackTraceWithCurrentObjects;
use PrettyPrinter\HasLocalVariables;
use PrettyPrinter\Utils\Table;
use PrettyPrinter\Utils\Text;
use PrettyPrinter\TypeHandler;

final class Exception extends TypeHandler
{
	/**
	 * @param \ReflectionProperty|\ReflectionMethod $property
	 *
	 * @return string
	 */
	static function propertyOrMethodAccess( $property )
	{
		return $property->isPrivate() ? 'private' : ( $property->isPublic() ? 'public' : 'protected' );
	}

	/**
	 * @param \Exception $exception
	 *
	 * @return \PrettyPrinter\Utils\Text
	 */
	function handleValue( &$exception )
	{
		$result = $this->prettyPrintExceptionWithoutGlobals( $exception );

		if ( $this->settings()->showExceptionGlobalVariables()->get() )
		{
			$result->addLine( 'global variables:' );
			$result->addLines( $this->prettyPrintGlobalState()->indent() );
			$result->addLine();
		}

		return $result;
	}

	private function prettyPrintGlobalState()
	{
		$superGlobals = array_fill_keys( array( '_POST',
		                                        '_GET',
		                                        '_SESSION',
		                                        '_COOKIE',
		                                        '_FILES',
		                                        '_REQUEST',
		                                        '_ENV',
		                                        '_SERVER' ), true );
		$globals      = array();

		foreach ( $GLOBALS as $name => &$value )
			if ( $name !== 'GLOBALS' )
				$globals[ ] = array( isset( $superGlobals[ $name ] ) ? '' : 'global ', $name, &$value );

		foreach ( get_declared_classes() as $class )
		{
			$reflection = new \ReflectionClass( $class );

			foreach ( $reflection->getProperties( \ReflectionProperty::IS_STATIC ) as $property )
			{
				$property->setAccessible( true );

				$access     = self::propertyOrMethodAccess( $property );
				$globals[ ] = array( "$access static $property->class::", $property->name, $property->getValue() );
			}

			foreach ( $reflection->getMethods() as $method )
				foreach ( $method->getStaticVariables() as $variable => $value )
				{
					$access     = self::propertyOrMethodAccess( $method );
					$globals[ ] = array( "$access function $class::$method->name()::static ", $variable, &$value );
				}
		}

		foreach ( get_defined_functions() as $section )
		{
			foreach ( $section as $function )
			{
				$reflection = new \ReflectionFunction( $function );

				foreach ( $reflection->getStaticVariables() as $variable => $value )
					$globals[ ] = array( "function $reflection->name()::static ", $variable, &$value );
			}
		}

		if ( empty( $globals ) )
			return new Text( 'none' );

		$table = new Table;

		foreach ( $globals as &$global )
			$table->addRow( array( $this->prettyPrintVariable( $global[ 1 ] )->prepend( $global[ 0 ] ),
			                       $this->prettyPrintRef( $global[ 2 ] )->wrap( ' = ', ';' ) ) );

		return $table->render();
	}

	private function prettyPrintVariables( array $variables )
	{
		$table = new Table;

		foreach ( $variables as $name => &$value )
			$table->addRow( array( $this->prettyPrintVariable( $name ),
			                       $this->prettyPrintRef( $value )->wrap( ' = ', ';' ) ) );

		return $table->render();
	}

	private function prettyPrintExceptionWithoutGlobals( \Exception $e )
	{
		$result = new Text;

		$result->addLine( get_class( $e ) . " {$e->getCode()} in {$e->getFile()}:{$e->getLine()}" );
		$result->addLine();
		$result->addLines( Text::create( $e->getMessage() )->indent( 2 ) );
		$result->addLine();

		if ( $this->settings()->showExceptionLocalVariables()->get() &&
		     $e instanceof HasLocalVariables &&
		     $e->getLocalVariables() !== null
		)
		{
			$result->addLine( "local variables:" );
			$result->addLines( $this->prettyPrintVariables( $e->getLocalVariables() )->indent() );
			$result->addLine();
		}

		if ( $this->settings()->showExceptionStackTrace()->get() )
		{
			$result->addLine( "stack trace:" );
			$result->addLines( $this->prettyPrintStackTrace( $e instanceof HasStackTraceWithCurrentObjects
					                                                 ? $e->getStackTraceWithCurrentObjects()
					                                                 : $e->getTrace() )->indent() );
			$result->addLine();
		}

		if ( $e->getPrevious() !== null )
		{
			$result->addLine( "previous exception:" );
			$result->addLines( $this->prettyPrintExceptionWithoutGlobals( $e->getPrevious() )->indent() );
			$result->addLine();
		}

		return $result;
	}

	private function prettyPrintStackTrace( array $stackTrace )
	{
		$result = new Text;
		$i      = 1;

		foreach ( $stackTrace as $stackFrame )
		{
			$result->addLine( "#$i $stackFrame[file]:$stackFrame[line]" );
			$result->addLines( $this->prettyPrintFunctionCall( $stackFrame )->indent( 3 ) );
			$result->addLine();
			$i++;
		}

		return $result->addLine( "#$i {main}" );
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		$object = isset( $stackFrame[ 'object' ] )
				? $this->prettyPrintRef( $stackFrame[ 'object' ] )
				: new Text( ArrayUtil::get( $stackFrame, 'class', '' ) );

		$arguments = isset( $stackFrame[ 'args' ] )
				? $this->prettyPrintFunctionArgs( $stackFrame[ 'args' ] )
				: new Text( '( ? )' );

		return Text::create()
		       ->appendLines( $object )
		       ->append( ArrayUtil::get( $stackFrame, 'type', '' ) )
		       ->append( ArrayUtil::get( $stackFrame, 'function', '' ) )
		       ->appendLines( $arguments )
		       ->append( ';' );
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return new Text( '()' );

		$result = new Text;

		foreach ( $args as $k => &$arg )
		{
			if ( $k !== 0 )
				$result->append( ', ' );

			$result->appendLines( $this->prettyPrintRef( $arg ) );
		}

		return $result->wrap( '( ', ' )' );
	}
}
