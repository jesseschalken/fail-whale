<?php

final class ExceptionPrettyPrinter extends AbstractPrettyPrinter
{
	/**
	 * @param Exception $exception
	 *
	 * @return string[]
	 */
	public function doPrettyPrint( &$exception )
	{
		$lines = $this->prettyPrintExceptionNoGlobals( $exception );

		$lines[] = '';
		$lines[] = 'global variables:';

		self::appendLines( $lines, self::indentLines( $this->prettyPrintVariables( self::globals() ) ) );

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
		$lines[] = 'uncaught ' . get_class( $e );

		$descriptionRows = array( array( array( "code " ), array( $e->getCode() ) ),
		                          array( array( "message " ), explode( "\n", $e->getMessage() ) ),
		                          array( array( "file " ), $this->prettyPrintLines( $e->getFile() ) ),
		                          array( array( "line " ), $this->prettyPrintLines( $e->getLine() ) ) );

		self::appendLines( $lines, self::indentLines( self::renderRowsAligned( $descriptionRows ) ) );

		if ( $e instanceof ExceptionWithLocalVariables && $e->getLocalVariables() !== null ) {
			$lines[] = "";
			$lines[] = "local variables:";

			self::appendLines( $lines, self::indentLines( $this->prettyPrintVariables( $e->getLocalVariables() ) ) );
		}

		$lines[] = "";
		$lines[] = "stack trace:";

		$stackTrace = $e instanceof ExceptionWithFullStackTrace ? $e->getFullStackTrace() : $e->getTrace();

		self::appendLines( $lines, self::indentLines( $this->prettyPrintStackTrace( $stackTrace ) ) );

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null ) {
			$lines[] = "";
			$lines[] = "previous exception:";

			self::appendLines( $lines, self::indentLines( $this->prettyPrintExceptionNoGlobals( $e->getPrevious() ) ) );
		}

		return $lines;
	}

	private function prettyPrintStackTrace( array $stackTrace )
	{
		$lines = array();

		foreach ( $stackTrace as $stackFrame ) {
			$fileAndLine = self::concatenateAligned( array( array( '- file ' ),
			                                                $this->prettyPrintRefLines( $stackFrame['file'] ),
			                                                array( ', line ' ),
			                                                $this->prettyPrintRefLines( $stackFrame['line'] ) ) );

			self::appendLines( $lines,
			                   array_merge( $fileAndLine,
			                                self::indentLines( $this->prettyPrintFunctionCall( $stackFrame ) ),
			                                array( '' ) ) );
		}

		$lines[] = '- {main}';

		return $lines;
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		if ( isset( $stackFrame['object'] ) )
			$object = $this->prettyPrintLines( $stackFrame['object'] );
		else
			$object = array( self::arrayGetDefault( $stackFrame, 'class', '' ) );

		if ( isset( $stackFrame['args'] ) )
			$args = $this->prettyPrintFunctionArgs( $stackFrame['args'] );
		else
			$args = array( '( ? )' );

		return self::concatenateAligned( array( $object,
		                                        array( self::arrayGetDefault( $stackFrame, 'type', '' ) .
		                                               self::arrayGetDefault( $stackFrame, 'function', '' ) ),
		                                        $args,
		                                        array( ';' ) ) );
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return array( '()' );

		$pieces[] = array( '( ' );

		foreach ( $args as $k => &$arg ) {
			if ( $k !== 0 )
				$pieces[] = array( ', ' );

			$pieces[] = $this->prettyPrintRefLines( $arg );
		}

		$pieces[] = array( ' )' );

		return self::concatenateAligned( $pieces );
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
