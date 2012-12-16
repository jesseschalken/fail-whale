<?php

class ErrorHandler
{
	private $lastError = null;

	public static function create()
	{
		return new self;
	}

	protected function __construct()
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

	public final function handleFailedAssertion( $file, $line, $expression, $message = 'Assertion failed' )
	{
		throw new AssertionFailedException( $file, $line, $expression, $message, self::fullStackTrace() );
	}

	public final function handleError( $severity, $message, $file = null, $line = null, $localVariables = null )
	{
		if ( error_reporting() & $severity ) {
			$e = new FullErrorException( $severity, $message, $file, $line, $localVariables, self::fullStackTrace() );

			if ( $severity & ( E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED ) )
				throw $e;

			$this->handleUncaughtException( $e );
		}

		$this->lastError = error_get_last();
	}

	public final function handleUncaughtException( Exception $e )
	{
		$this->handleException( $e );

		$this->lastError = error_get_last();
		exit( 1 );
	}

	public final function handleShutdown()
	{
		$error = error_get_last();

		if ( $error !== null && $error !== $this->lastError )
			$this->handleUncaughtException( new FullErrorException( $error['type'], $error['message'], $error['file'], $error['line'], null, self::fullStackTrace() ) );
	}

	private static function fullStackTrace()
	{
		$trace = debug_backtrace();

		array_shift( $trace );
		array_shift( $trace );

		return $trace;
	}

	protected function handleException( Exception $e )
	{
		self::out( ExceptionPrettyPrinter::prettyPrintExceptionOneLine( $e ),
		           ExceptionPrettyPrinter::prettyPrintException( $e ) );
	}

	protected static function out( $title, $body )
	{
		while ( ob_get_level() > 0 )
			ob_end_clean();

		if ( PHP_SAPI === 'cli' ) {
			print $body;
		} else {
			if ( !headers_sent() ) {
				header( 'HTTP/1.1 500 Internal Server Error', true, 500 );
				header( "Content-Type: text/html; charset=UTF-8", true );
			}

			print self::wrapHtml( $title, $body );
		}
	}

	private static function toHtml( $text )
	{
		return htmlspecialchars( $text, ENT_COMPAT, "UTF-8" );
	}

	protected static function wrapHtml( $title, $body )
	{
		$body  = self::toHtml( $body );
		$title = self::toHtml( $title );

		return <<<html
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>$title</title>
	</head>
	<body>
		<pre style="
			white-space: pre;
			font-family: 'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace;
			font-size: 10pt;
			color: #000000;
			display: block;
			background: white;
			border: none;
			margin: 0;
			padding: 0;
			line-height: 16px;
			width: 100%;
		">$body</pre>
	</body>
</html>
html;
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

class AssertionFailedException extends Exception implements ExceptionWithFullStackTrace
{
	private $expression;
	private $fullStackTrace;

	/**
	 * @param string    $file
	 * @param int       $line
	 * @param string    $expression
	 * @param string    $message
	 * @param array     $fullStackTrace
	 */
	public function __construct( $file, $line, $expression, $message, array $fullStackTrace )
	{
		$this->file           = $file;
		$this->line           = $line;
		$this->expression     = $expression;
		$this->message        = $message;
		$this->fullStackTrace = $fullStackTrace;
	}

	public function getFullStackTrace()
	{
		return $this->fullStackTrace;
	}
}

class FullErrorException extends ErrorException implements ExceptionWithLocalVariables, ExceptionWithFullStackTrace
{
	private $localVariables = array();
	private $fullStackTrace = array();
	private static $errorConstants = array( E_ERROR             => 'E_ERROR',
	                                        E_WARNING           => 'E_WARNING',
	                                        E_PARSE             => 'E_PARSE',
	                                        E_NOTICE            => 'E_NOTICE',
	                                        E_CORE_ERROR        => 'E_CORE_ERROR',
	                                        E_CORE_WARNING      => 'E_CORE_WARNING',
	                                        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
	                                        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
	                                        E_USER_ERROR        => 'E_USER_ERROR',
	                                        E_USER_WARNING      => 'E_USER_WARNING',
	                                        E_USER_NOTICE       => 'E_USER_NOTICE',
	                                        E_STRICT            => 'E_STRICT',
	                                        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
	                                        E_DEPRECATED        => 'E_DEPRECATED',
	                                        E_USER_DEPRECATED   => 'E_USER_DEPRECATED' );

	/**
	 * @param int          $severity
	 * @param string       $message
	 * @param string|null  $file
	 * @param int|null     $line
	 * @param array|null   $localVariables
	 * @param array|null   $fullStackTrace
	 */
	public function __construct( $severity,
	                             $message,
	                             $file,
	                             $line,
	                             array $localVariables = null,
	                             array $fullStackTrace = null )
	{
		parent::__construct( $message, 0, $severity, $file, $line );

		$this->localVariables = $localVariables;
		$this->fullStackTrace = $fullStackTrace;
		$this->code           = isset( self::$errorConstants[$severity] ) ? self::$errorConstants[$severity] : 'E_?';
	}

	public function getFullStackTrace()
	{
		return $this->fullStackTrace !== null ? $this->fullStackTrace : $this->getTrace();
	}

	public function getLocalVariables()
	{
		return $this->localVariables;
	}
}
