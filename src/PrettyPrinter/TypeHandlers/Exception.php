<?php

namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\ExceptionInfo;
use PrettyPrinter\TypeHandler;
use PrettyPrinter\Utils\ArrayUtil;
use PrettyPrinter\Utils\Table;
use PrettyPrinter\Utils\Text;

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
	 * @param ExceptionInfo $exception
	 *
	 * @return \PrettyPrinter\Utils\Text
	 */
	function handleValue( &$exception )
	{
		return $this->prettyPrintExceptionWithoutGlobals( $exception )
		       ->addLines( $this->prettyPrintGlobalState( $exception ) );
	}

	private function prettyPrintGlobalState( ExceptionInfo $exception )
	{
		if ( !$this->settings()->showExceptionGlobalVariables()->get() )
			return new Text;

		return $this->prettyPrintGlobalVariables( $exception )->indent()->wrapLines( 'global variables:' );
	}

	private function prettyPrintGlobalVariables( ExceptionInfo $exception )
	{
		$globals = $exception->globalVariables();

		if ( empty( $globals ) )
			return new Text( 'none' );

		$table = new Table;

		foreach ( $globals as $global )
			$table->addRow( array( $global->prettyPrint( $this ),
			                       $this->prettyPrintRef( $global->value() )->wrap( ' = ', ';' ) ) );

		return $table->render();
	}

	private function prettyPrintLocalVariables( ExceptionInfo $exception )
	{
		if ( !$this->settings()->showExceptionLocalVariables()->get() )
			return new Text;

		if ( $exception->localVariables() === null )
			return new Text;

		$table = new Table;

		foreach ( $exception->localVariables() as $name => $value )
			$table->addRow( array( $this->prettyPrintVariable( $name ),
			                       $this->prettyPrintRef( $value )->wrap( ' = ', ';' ) ) );

		return $table->render()->indent()->wrapLines( "local variables:" );
	}

	private function prettyPrintExceptionWithoutGlobals( ExceptionInfo $e )
	{
		return Text::create()
		       ->addLines( $this->prettyPrintExceptionHeader( $e ) )
		       ->addLines( $this->prettyPrintLocalVariables( $e ) )
		       ->addLines( $this->prettyPrintStackTrace( $e ) )
		       ->addLines( $this->prettyPrintPreviousException( $e ) );
	}

	private function prettyPrintExceptionHeader( ExceptionInfo $e )
	{
		$class = $e->exceptionClassName();
		$code  = $e->code();
		$file  = $e->file();
		$line  = $e->line();

		return Text::create( "$class $code in $file:$line" )
		       ->addLines( Text::create( $e->message() )->indent( 2 )->wrapLines() );
	}

	private function prettyPrintPreviousException( ExceptionInfo $exception )
	{
		if ( $exception->previous() === null )
			return new Text;

		return $this->prettyPrintExceptionWithoutGlobals( $exception->previous() )->indent( 2 )->wrapLines( "previous exception:" );
	}

	private function prettyPrintStackTrace( ExceptionInfo $exception )
	{
		if ( !$this->settings()->showExceptionStackTrace()->get() )
			return new Text;

		$result = new Text;
		$i      = 1;

		foreach ( $exception->stackTrace() as $stackFrame )
		{
			$result->addLine( "#$i $stackFrame[file]:$stackFrame[line]" );
			$result->addLines( $this->prettyPrintFunctionCall( $stackFrame )->indent( 3 ) );
			$result->addLine();
			$i++;
		}

		return $result->addLine( "#$i {main}" )->indent()->wrapLines( "stack trace:" );
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		return Text::create()
		       ->appendLines( $this->prettyPrintFunctionObject( $stackFrame ) )
		       ->append( ArrayUtil::get( $stackFrame, 'type', '' ) )
		       ->append( ArrayUtil::get( $stackFrame, 'function', '' ) )
		       ->appendLines( $this->prettyPrintFunctionArgs( $stackFrame ) )
		       ->append( ';' );
	}

	private function prettyPrintFunctionObject( array $stackFrame )
	{
		$object = ArrayUtil::get( $stackFrame, 'object' );

		if ( !isset( $object ) )
			return new Text( ArrayUtil::get( $stackFrame, 'class', '' ) );

		return $this->prettyPrint( $object );
	}

	private function prettyPrintFunctionArgs( array $stackFrame )
	{
		$args = ArrayUtil::get( $stackFrame, 'args' );

		if ( !isset( $args ) )
			return new Text( '( ? )' );

		if ( empty( $args ) )
			return new Text( '()' );

		$pretties    = array();
		$isMultiLine = false;
		$result      = new Text;

		foreach ( $args as &$arg )
		{
			$pretty      = $this->prettyPrintRef( $arg );
			$isMultiLine = $isMultiLine || $pretty->count() > 1;
			$pretties[ ] = $pretty;
		}

		foreach ( $pretties as $k => $pretty )
		{
			if ( $k !== 0 )
				$result->append( ', ' );

			if ( $isMultiLine )
				$result->addLines( $pretty );
			else
				$result->appendLines( $pretty );
		}

		return $result->wrap( '( ', ' )' );
	}
}
