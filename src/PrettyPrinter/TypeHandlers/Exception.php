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
	private static function globals()
	{
		/**
		 * Don't ask me why, but if I don't send $GLOBALS through array_merge(), unset( $globals['GLOBALS'] ) (next
		 * line) ends up removing the $GLOBALS super global itself.
		 */
		$globals = array_merge( $GLOBALS );
		unset( $globals[ 'GLOBALS' ] );

		return $globals;
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
			$result->addLines( $this->prettyPrintVariables( self::globals() )->indent() );
			$result->addLine();
		}

		return $result;
	}

	private function prettyPrintVariables( array $variables )
	{
		if ( empty( $variables ) )
			return new Text( 'none' );

		$table = new Table;

		foreach ( $variables as $k => &$v )
			$table->addRow( array(
			                     $this->prettyPrintVariable( $k ),
			                     new Text( ' = ' ),
			                     $this->prettyPrintRef( $v )->append( ';' ),
			                ) );

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

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null )
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
			$result->addLine( "#$i {$stackFrame['file']}:{$stackFrame['line']}" );
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
				: new Text( ArrayUtil::get( $stackFrame, 'class' ) );

		$arguments = isset( $stackFrame[ 'args'] )
				? $this->prettyPrintFunctionArgs( $stackFrame[ 'args' ] )
				: new Text( '( ? )' );

		return Text::create()
		       ->appendLines( $object )
		       ->append( ArrayUtil::get( $stackFrame, 'type' ) )
		       ->append( ArrayUtil::get( $stackFrame, 'function' ) )
		       ->appendLines( $arguments )
		       ->append( ';' );
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return new Text( '()' );

		$result = new Text;

		foreach ( $args as $k => &$arg )
			$result->append( $k === 0 ? '' : ', ' )->appendLines( $this->prettyPrintRef( $arg ) );

		return $result->wrap( '( ', ' )' );
	}
}
