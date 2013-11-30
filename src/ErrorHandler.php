<?php

namespace ErrorHandler
{
	use PrettyPrinter\HasFullTrace;
	use PrettyPrinter\HasLocalVariables;
	use PrettyPrinter\PrettyPrinter;
	use PrettyPrinter\Utils\ArrayUtil;

	class ErrorHandler
	{
		static function create() { return new self; }

		static function traceWithoutThis()
		{
			$trace  = debug_backtrace();
			$object = ArrayUtil::get2( $trace, 1, 'object' );
			$i      = 2;

			while ( ArrayUtil::get2( $trace, $i, 'object' ) === $object )
				$i++;

			return array_slice( $trace, $i );
		}

		protected static function out( $title, $body )
		{
			while ( ob_get_level() > 0 && ob_end_clean() )
				;

			if ( PHP_SAPI === 'cli' )
			{
				fwrite( STDERR, $body );
			}
			else
			{
				if ( !headers_sent() )
				{
					header( 'HTTP/1.1 500 Internal Server Error', true, 500 );
					header( "Content-Type: text/html; charset=UTF-8", true );
				}

				print self::wrapHtml( $title, $body );
			}
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

		private static function toHtml( $text )
		{
			return htmlspecialchars( $text, ENT_COMPAT, "UTF-8" );
		}

		private $lastError;

		protected function __construct() { }

		final function bind()
		{
			ini_set( 'display_errors', false );
			ini_set( 'log_errors', false );
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

		final function handleFailedAssertion( $file, $line, $expression, $message = 'Assertion failed' )
		{
			throw new AssertionFailedException( $file, $line, $expression, $message, self::traceWithoutThis() );
		}

		final function handleError( $severity, $message, $file = null, $line = null, $localVariables = null )
		{
			if ( error_reporting() & $severity )
			{
				$e = new ErrorException( $severity, $message, $file, $line, $localVariables, self::traceWithoutThis() );

				if ( $severity & ( E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED ) )
					throw $e;

				$this->handleUncaughtException( $e );
			}

			$this->lastError = error_get_last();

			return true;
		}

		final function handleUncaughtException( \Exception $e )
		{
			$this->handleException( $e );

			$this->lastError = error_get_last();
			exit( 1 );
		}

		final function handleShutdown()
		{
			ini_set( 'memory_limit', '-1' );

			$error = error_get_last();

			if ( $error === null || $error === $this->lastError || !$this->isCurrentErrorHandler() )
				return;

			$this->handleUncaughtException( new ErrorException( $error[ 'type' ], $error[ 'message' ], $error[ 'file' ],
			                                                    $error[ 'line' ], null, self::traceWithoutThis() ) );
		}

		private function isCurrentErrorHandler()
		{
			$handler = set_error_handler( function () { } );

			restore_error_handler();

			return $handler === array( $this, 'handleError' );
		}

		protected function handleException( \Exception $e )
		{
			self::out( 'error', PrettyPrinter::create()
			                                 ->maxStringLength()->set( 100 )
			                                 ->maxArrayEntries()->set( 10 )
			                                 ->maxObjectProperties()->set( 10 )
			                                 ->prettyPrintException( $e ) );
		}
	}

	class ErrorException extends \ErrorException implements HasFullTrace, HasLocalVariables
	{
		private $localVariables, $stackTrace;

		function __construct( $severity, $message, $file, $line, array $localVariables = null, array $stackTrace )
		{
			parent::__construct( $message, 0, $severity, $file, $line );

			$this->localVariables = $localVariables;
			$this->stackTrace     = $stackTrace;
			$this->code           = ArrayUtil::get( array( E_ERROR             => 'E_ERROR',
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
			                                               E_USER_DEPRECATED   => 'E_USER_DEPRECATED' ), $severity,
			                                        'E_?' );
		}

		function getFullTrace() { return $this->stackTrace; }

		function getLocalVariables() { return $this->localVariables; }
	}

	class AssertionFailedException extends \Exception implements HasFullTrace
	{
		private $expression, $fullStackTrace;

		/**
		 * @param string $file
		 * @param int    $line
		 * @param string $expression
		 * @param string $message
		 * @param array  $fullStackTrace
		 */
		function __construct( $file, $line, $expression, $message, array $fullStackTrace )
		{
			$this->file           = $file;
			$this->line           = $line;
			$this->expression     = $expression;
			$this->message        = $message;
			$this->fullStackTrace = $fullStackTrace;
		}

		function getFullTrace() { return $this->fullStackTrace; }
	}

	/**
	 * Same as \Exception except it includes a full stack trace
	 *
	 * @package ErrorHandler
	 */
	class Exception extends \Exception implements HasFullTrace
	{
		private $stackTrace;

		function __construct( $message = "", $code = 0, \Exception $previous = null )
		{
			$this->stackTrace = ErrorHandler::traceWithoutThis();

			parent::__construct( $message, $code, $previous );
		}

		function getFullTrace() { return $this->stackTrace; }
	}
}
