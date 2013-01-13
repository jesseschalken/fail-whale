<?php

final class ExceptionPrettyPrinter extends AbstractPrettyPrinter
{
	/**
	 * @param Exception $exception
	 *
	 * @return PrettyPrinterLines
	 */
	public function doPrettyPrint( &$exception )
	{
		$lines = $this->prettyPrintExceptionNoGlobals( $exception );

		if ( $this->settings()->showExceptionGlobalVariables()->isYes() )
		{
			$lines->addLine( 'global variables:' );
			$lines->addLines( $this->prettyPrintVariables( self::globals() )->indent() );
			$lines->addLine();
		}

		return $lines;
	}

	private static function globals()
	{
		/**
		 * Don't ask me why, but if I don't send $GLOBALS through array_merge(), unset( $globals['GLOBALS'] ) (next
		 * line) ends up removing the $GLOBALS superglobal itself.
		 */
		$globals = array_merge( $GLOBALS );
		unset( $globals['GLOBALS'] );

		return $globals;
	}

	private function prettyPrintExceptionNoGlobals( Exception $e )
	{
		$lines = self::lines();
		$lines->addLine( get_class( $e ) . ' ' . $e->getCode() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		$lines->addLine();
		$lines->addLines( PrettyPrinterLines::split( $e->getMessage() )->indent( '    ' ) );
		$lines->addLine();

		if ( $this->settings()->showExceptionLocalVariables()->isYes() && $e instanceof ExceptionWithLocalVariables
		     && $e->getLocalVariables() !== null
		)
		{
			$lines->addLine( "local variables:" );
			$lines->addLines( $this->prettyPrintVariables( $e->getLocalVariables() )->indent() );
			$lines->addLine();
		}

		if ( $this->settings()->showExceptionStackTrace()->isYes() )
		{
			$lines->addLine( "stack trace:" );

			$stackTrace = $e instanceof ExceptionWithFullStackTrace ? $e->getFullStackTrace() : $e->getTrace();
			$lines->addLines( $this->prettyPrintStackTrace( $stackTrace )->indent() );
			$lines->addLine();
		}

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null )
		{
			$lines->addLine( "previous exception:" );
			$lines->addLines( $this->prettyPrintExceptionNoGlobals( $e->getPrevious() )->indent() );
			$lines->addLine();
		}

		return $lines;
	}

	private function prettyPrintStackTrace( array $stackTrace )
	{
		$lines = self::lines();
		$i     = 1;

		foreach ( $stackTrace as $stackFrame )
		{
			$lines->addLine( "#$i {$stackFrame['file']}:{$stackFrame['line']}" );
			$lines->addLines( $this->prettyPrintFunctionCall( $stackFrame )->indent( '      ' ) );
			$lines->addLine();
			$i++;
		}

		$lines->addLine( "#$i {main}" );

		return $lines;
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		$lines = self::lines();

		if ( isset( $stackFrame['object'] ) )
			$lines->appendLinesAligned( $this->prettyPrintRef( $stackFrame['object'] ) );
		else if ( isset( $stackFrame['class'] ) )
			$lines->append( $stackFrame['class'] );

		if ( isset( $stackFrame['type'] ) )
			$lines->append( $stackFrame['type'] );

		if ( isset( $stackFrame['function'] ) )
			$lines->append( $stackFrame['function'] );

		if ( isset( $stackFrame['args'] ) )
			$lines->appendLinesAligned( $this->prettyPrintFunctionArgs( $stackFrame['args'] ) );
		else
			$lines->append( '( ? )' );

		$lines->append( ';' );

		return $lines;
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return self::line( '()' );

		$lines = self::lines();

		foreach ( $args as $k => &$arg )
			$lines->append( $k === 0 ? '' : ', ' )->appendLinesAligned( $this->prettyPrintRef( $arg ) );

		return $lines->wrapAligned( '( ', ' )' );
	}
}

interface ExceptionWithLocalVariables
{
	/**
	 * @return array
	 */
	public function getLocalVariables();
}

interface ExceptionWithFullStackTrace
{
	/**
	 * @return array
	 */
	public function getFullStackTrace();
}
