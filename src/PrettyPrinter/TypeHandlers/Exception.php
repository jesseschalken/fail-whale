<?php

namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\ArrayUtil;
use PrettyPrinter\HasFullStackTrace;
use PrettyPrinter\HasLocalVariables;
use PrettyPrinter\Table;
use PrettyPrinter\Text;
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
	 * @return Text
	 */
	function handleValue( &$exception )
	{
		$result = $this->prettyPrintExceptionNoGlobals( $exception );

		if ( $this->settings()->showExceptionGlobalVariables )
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
			return Text::line( 'none' );

		$table = new Table;

		foreach ( $variables as $k => &$v )
			$table->addRow( array(
			                     $this->prettyPrintVariable( $k ),
			                     Text::line( ' = ' ),
			                     $this->prettyPrintRef( $v )->append( ';' ),
			                ) );

		return $table->render();
	}

	private function prettyPrintExceptionNoGlobals( \Exception $e )
	{
		$result = new Text;

		$result->addLine( get_class( $e ) . " {$e->getCode()} in {$e->getFile()}:{$e->getLine()}" );
		$result->addLine();
		$result->addLines( Text::split( $e->getMessage() )->indent( '    ' ) );
		$result->addLine();

		if ( $this->settings()->showExceptionLocalVariables && $e instanceof HasLocalVariables
		     && $e->getLocalVariables() !== null
		)
		{
			$result->addLine( "local variables:" );
			$result->addLines( $this->prettyPrintVariables( $e->getLocalVariables() )->indent() );
			$result->addLine();
		}

		if ( $this->settings()->showExceptionStackTrace )
		{
			$result->addLine( "stack trace:" );

			$result->addLines( $this->prettyPrintStackTrace( $e instanceof HasFullStackTrace
					                                                 ? $e->getFullStackTrace()
					                                                 : $e->getTrace() )->indent() );
			$result->addLine();
		}

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null )
		{
			$result->addLine( "previous exception:" );
			$result->addLines( $this->prettyPrintExceptionNoGlobals( $e->getPrevious() )->indent() );
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
			$result->addLines( $this->prettyPrintFunctionCall( $stackFrame )->indent( '      ' ) );
			$result->addLine();
			$i++;
		}

		return $result->addLine( "#$i {main}" );
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		$result = new Text;

		if ( isset( $stackFrame[ 'object' ] ) )
			$result->appendLinesAligned( $this->prettyPrintRef( $stackFrame[ 'object' ] ) );
		else
			$result->append( ArrayUtil::get( $stackFrame, 'class', '' ) );

		$result->append( ArrayUtil::get( $stackFrame, 'type', '' ) . ArrayUtil::get( $stackFrame, 'function', '' ) );

		if ( isset( $stackFrame[ 'args' ] ) )
			$result->appendLinesAligned( $this->prettyPrintFunctionArgs( $stackFrame[ 'args' ] ) );
		else
			$result->append( '( ? )' );

		return $result->append( ';' );
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return Text::line( '()' );

		$result = new Text;

		foreach ( $args as $k => &$arg )
			$result->append( $k === 0 ? '' : ', ' )->appendLinesAligned( $this->prettyPrintRef( $arg ) );

		return $result->wrapAligned( '( ', ' )' );
	}
}
