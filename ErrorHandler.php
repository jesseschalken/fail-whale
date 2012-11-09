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
		throw new AssertionFailedException( $file, $line, $expression, $message );
	}

	private function isErrorThrowable( PhpErrorException $e )
	{
		if ( $e->getSeverity() & ( E_USER_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED ) )
			return true;

		return false;
	}

	public final function handleError( $severity, $message, $file = null, $line = null, $context = null )
	{
		if ( error_reporting() & $severity )
		{
			$fullTrace = debug_backtrace();
			array_shift( $fullTrace );

			$e = new PhpErrorException( $severity, $message, $file, $line, $context, $fullTrace );

			if ( $this->isErrorThrowable( $e ) )
				throw $e;
			else
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

		if ( $error === null || $error === $this->lastError )
			return;

		$fullTrace = debug_backtrace();
		array_shift( $fullTrace );

		$e = new PhpErrorException( $error['type'], $error['message'], $error['file'], $error['line'], null, $fullTrace );

		$this->handleUncaughtException( $e );
	}

	protected function handleException( Exception $e )
	{
		self::out( self::joinLines( PhpDump::dumpExceptionLines( $e ) ) );
	}

	protected static function out( $text )
	{
		while ( ob_get_level() > 0 )
			ob_end_clean();

		if ( PHP_SAPI === 'cli' )
			print $text;
		else
		{
			if ( !headers_sent() )
			{
				header( 'HTTP/1.1 500 Internal Server Error', true, 500 );
				header( "Content-Type: text/html; charset=UTF-8", true );
			}

			print self::wrapHtml( $text );
		}
	}

	protected static function joinLines( array $lines )
	{
		return empty( $lines ) ? '' : join( PHP_EOL, $lines ) . PHP_EOL;
	}

	private static function wrapHtml( $text )
	{
		$html = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );

		return <<<eot
<div style="
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
">$html</div>
eot;
	}
}

class AssertionFailedException extends Exception
{
	private $expression;

	/**
	 * @param string    $file
	 * @param int       $line
	 * @param string    $expression
	 * @param string    $message
	 */
	public function __construct( $file, $line, $expression, $message )
	{
		$this->file       = $file;
		$this->line       = $line;
		$this->expression = $expression;
		$this->message    = $message;
	}
}

class PhpErrorException extends ErrorException
{
	private $context = array();
	private $fullTrace = array();
	private static $errorConstants = array(
		E_ERROR             => 'E_ERROR',
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
		E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
	);

	/**
	 * @param int          $severity
	 * @param string       $message
	 * @param string|null  $file
	 * @param int|null     $line
	 * @param array|null   $context
	 * @param array|null   $fullTrace
	 */
	public function __construct( $severity, $message, $file, $line, array $context = null, array $fullTrace = null )
	{
		parent::__construct( $message, 0, $severity, $file, $line );

		$this->context   = $context;
		$this->fullTrace = $fullTrace;
		$this->code      = isset( self::$errorConstants[$severity] ) ? self::$errorConstants[$severity] : 'E_?';
	}

	public function getFullTrace()
	{
		return $this->fullTrace !== null ? $this->fullTrace : $this->getTrace();
	}

	public function getContext()
	{
		return $this->context;
	}
}
