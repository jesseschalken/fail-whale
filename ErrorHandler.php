<?php

class ErrorHandler
{
  public function __construct()
  {
  }

  public final function bind()
  {
    ini_set( 'display_errors' , false );
    ini_set( 'html_errors'    , false );

    assert_options( ASSERT_ACTIVE     , true   );
    assert_options( ASSERT_WARNING    , true   );
    assert_options( ASSERT_BAIL       , false  );
    assert_options( ASSERT_QUIET_EVAL , false  );
    assert_options( ASSERT_CALLBACK   , array( $this, 'handleFailedAssertion' ) );

    set_error_handler          ( array( $this, 'handleError'             ) );
    set_exception_handler      ( array( $this, 'handleUncaughtException' ) );
    register_shutdown_function ( array( $this, 'handleShutdown'          ) );
  }

  public final function handleFailedAssertion( $file, $line, $message )
  {
    throw new CustomException( "Assertion failed: $message", null, $file, $line );
  }

  public final function handleError( $type, $message, $file, $line, $context, $skip = 0 )
  {
    // Note: See PHP bugs #61767 and #60909 as to why I can't just throw an exception.

    if ( error_reporting() & $type ) {
      $e = new ErrorException( $message, $type, null, $file, $line );
      $this->handleException( $e, self::trace( $skip + 1 ) );
      exit;
    }
  }

  public final function handleUncaughtException( Exception $e )
  {
    return $this->handleException( $e, $e->getTrace() );
  }

  public final function handleShutdown()
  {
    $e = error_get_last();

    if ( $e === null )
      return;

    error_reporting( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR );

    $this->handleError( $e['type'], $e['message'], $e['file'], $e['line'], 1 );
  }

  protected function handleException( Exception $e, array $trace )
  {
    while ( ob_get_level() > 0 )
      ob_end_clean();

    @header( 'HTTP/1.1 500 Internal Server Error', true, 500 );
    @header( "Content-Type: text/plain; charset=UTF-8", true );

    print 'uncaught ' . PhpDump::dumpExceptionWithTrace( $e, $trace ) . "\n";
  }

  private static function trace( $skip = 0 )
  {
    $trace = debug_backtrace();

    while ( $skip-- >= 0 )
      array_shift( $trace );

    return $trace;
  }
}

class CustomException extends Exception
{
  public function __construct( $message, $code, $file, $line, $previous = null )
  {
    parent::__construct( $message, $code, $previous );

    $this->file = $file;
    $this->line = $line;
  }
}

