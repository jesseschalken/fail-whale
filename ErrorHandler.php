<?php

class ErrorHandler
{
	private $lastError = null;

	public function __construct()
	{
	}

	public final function bind()
	{
		ini_set( 'display_errors', false );
		ini_set( 'html_errors', false );

		assert_options( ASSERT_ACTIVE, true );
		assert_options( ASSERT_WARNING, true );
		assert_options( ASSERT_BAIL, false );
		assert_options( ASSERT_QUIET_EVAL, false );
		assert_options( ASSERT_CALLBACK, array( $this, 'handleFailedAssertion' ) );

		set_error_handler( array( $this, 'handleError' ) );
		set_exception_handler( array( $this, 'handleUncaughtException' ) );
		register_shutdown_function( array( $this, 'handleShutdown' ) );

		$this->lastError = error_get_last();
	}

	public final function handleFailedAssertion( $file, $line, $code, $message = 'Assertion failed' )
	{
		throw new AssertionFailedException( $file, $line, $message );
	}

	private function isErrorThrowable( ErrorException $e )
	{
		$constants = array(
			'E_USER_ERROR',
			'E_USER_WARNING',
			'E_USER_NOTICE',
			'E_USER_DEPRECATED',
		);

		foreach ( $constants as $c )
			if ( @constant( $c ) === $e->getSeverity() )
				return true;

		return false;
	}

	public final function handleError( $type,
	                                   $message,
	                                   $file = null,
	                                   $line = null,
	                                   $context = null )
	{
		if ( error_reporting() & $type )
		{
			$fullTrace = debug_backtrace();
			array_shift( $fullTrace );

			$e = new PhpErrorException( $type, $message, $file, $line, $context, $fullTrace );

			if ( $this->isErrorThrowable( $e ) )
				throw $e;
			else
				$this->handleException( $e );

			$this->lastError = error_get_last();
			exit( 1 );
		}

		$this->lastError = error_get_last();
	}

	public final function handleUncaughtException( Exception $e )
	{
		$this->handleException( $e );

		exit( 1 );
	}

	public final function handleShutdown()
	{
		$error = error_get_last();

		if ( $error === null && $error === $this->lastError )
			return;

		$fullTrace = debug_backtrace();
		array_shift( $fullTrace );

		$e = new PhpErrorException( $error['type'], $error['message'], $error['file'], $error['line'], null, $fullTrace );

		$this->handleException( $e );
	}

	protected function handleException( Exception $e )
	{
		$this->out( PhpDump::dumpException( $e ) );
	}

	protected final function out( array $lines )
	{
		while ( ob_get_level() > 0 )
			ob_end_clean();

		if ( PHP_SAPI === 'cli' )
		{
			foreach ( $lines as $line )
				print $line . PHP_EOL;
		}
		else
		{
			if ( !headers_sent() )
			{
				header( 'HTTP/1.1 500 Internal Server Error', true, 500 );
				header( "Content-Type: text/html; charset=UTF-8", true );
			}

			print $this->wrapHtml( $lines );
		}
	}

	protected final function wrapHtml( array $lines )
	{
		$html = htmlspecialchars( join( "\n", $lines ), ENT_QUOTES, 'UTF-8' );

		return <<<eot
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>$html</title>
  </head>
  <body style="
      white-space: pre;
      font-family: 'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace;
      font-size: 10pt;
      color: black;
      display: block;
      background: white;
      border: none;
      margin: 0;
      padding: 0;
      line-height: 16px;
  ">$html</body>
</html>
eot;
	}
}

class AssertionFailedException extends Exception
{
	public function __construct( $file, $line, $message )
	{
		$this->message = $message;
		$this->file    = $file;
		$this->line    = $line;
	}
}

class PhpErrorException extends ErrorException
{
	private $context = array();
	private $fullTrace = array();

	public function __construct( $severity,
	                             $message,
	                             $file,
	                             $line,
	                             $context = null,
	                             $fullTrace = null )
	{
		$this->context   = $context;
		$this->fullTrace = $fullTrace;
		$this->message   = '(' . self::errorConstantName( $severity ) . ") $message";
		$this->file      = $file;
		$this->line      = $line;
		$this->severity  = $severity;
	}

	private static function errorConstantName( $severity )
	{
		$constants = array(
			'E_ERROR',
			'E_WARNING',
			'E_PARSE',
			'E_NOTICE',
			'E_CORE_ERROR',
			'E_CORE_WARNING',
			'E_COMPILE_ERROR',
			'E_COMPILE_WARNING',
			'E_USER_ERROR',
			'E_USER_WARNING',
			'E_USER_NOTICE',
			'E_STRICT',
			'E_RECOVERABLE_ERROR',
			'E_DEPRECATED',
			'E_USER_DEPRECATED',
		);

		foreach ( $constants as $c )
			if ( @constant( $c ) === $severity )
				return $c;

		return 'unknown';
	}

	public function getFullTrace()
	{
		if ( $this->fullTrace !== null )
			return $this->fullTrace;
		else
			return $this->getTrace();
	}

	public function getContext()
	{
		return $this->context;
	}
}

