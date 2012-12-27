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

		if ( $this->settings()->showExceptionGlobalVariables()->isYes() ) {
			$lines->addLine( '' );
			$lines->addLine( 'global variables:' );
			$lines->addLines( $this->prettyPrintVariables( self::globals() )->indent() );
		}

		$lines->addLine( '' );

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
		$lines = self::line( 'uncaught ' . get_class( $e ) );

		$descriptionTable = new PrettyPrinterTable;
		$descriptionTable->newRow()->addTextCell( 'code ' )->addTextCell( $e->getCode() );
		$descriptionTable->newRow()->addTextCell( 'message ' )
				->addCell( PrettyPrinterLines::split( $e->getMessage() ) );
		$descriptionTable->newRow()->addTextCell( 'file ' )->addCell( $this->prettyPrint( $e->getFile() ) );
		$descriptionTable->newRow()->addTextCell( 'line ' )->addCell( $this->prettyPrint( $e->getLine() ) );

		$lines->addLines( $descriptionTable->render()->indent() );

		if ( $this->settings()->showExceptionLocalVariables()->isYes() && $e instanceof ExceptionWithLocalVariables &&
		     $e->getLocalVariables() !== null
		) {
			$lines->addLine( '' );
			$lines->addLine( "local variables:" );
			$lines->addLines( $this->prettyPrintVariables( $e->getLocalVariables() )->indent() );
		}

		$lines->addLine( "" );
		$lines->addLine( "stack trace:" );

		$stackTrace = $e instanceof ExceptionWithFullStackTrace ? $e->getFullStackTrace() : $e->getTrace();
		$lines->addLines( $this->prettyPrintStackTrace( $stackTrace )->indent() );

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null ) {
			$lines->addLine( "" );
			$lines->addLine( "previous exception:" );
			$lines->addLines( $this->prettyPrintExceptionNoGlobals( $e->getPrevious() )->indent() );
		}

		return $lines;
	}

	private function prettyPrintStackTrace( array $stackTrace )
	{
		$lines = self::lines();

		foreach ( $stackTrace as $stackFrame ) {
			$lines->addLine( '- file ' )->appendLinesAligned( $this->prettyPrintRef( $stackFrame['file'] ) )
					->append( ', line ' )->appendLinesAligned( $this->prettyPrintRef( $stackFrame['line'] ) );

			$lines->addLines( $this->prettyPrintFunctionCall( $stackFrame )->indent() )->addLine( '' );
		}

		$lines->addLine( '- {main}' );

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
