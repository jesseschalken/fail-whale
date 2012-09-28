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
    throw new FailedAssertion( $file, $line, $message, self::trace( 1 ) );
  }

  public final function handleError( $type, $message, $file, $line )
  {
    if ( error_reporting() & $type ) {
      // Note: See PHP bugs #61767 and #60909 as to why I can't just throw an exception.
      $this->handleUncaughtException( new FullErrorException( $message, $type, $file, $line, self::trace( 1 ) ) );
      exit;
    }
  }

  public function handleUncaughtException( Exception $e )
  {
    while ( ob_get_level() > 0 )
      ob_end_clean();

    @header( 'HTTP/1.1 500 Internal Server Error', true, 500 );
    @header( "Content-Type: text/plain; charset=UTF-8", true );

    print 'uncaught ' . PhpDump::dumpException( $e ) . "\n";
  }

  public final function handleShutdown()
  {
    $error = error_get_last();

    if ( $error === null )
      return;

    $fatalTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    $message = $error['message'];
    $type    = $error['type'];
    $file    = $error['file'];
    $line    = $error['line'];

    if ( ( $type & $fatalTypes ) === 0 )
      return;

    $exception = new FullErrorException( $message, $type, $file, $line, self::trace( 1 ) );

    $this->handleUncaughtException( $exception );
  }

  private static function trace( $skip = 0 )
  {
    $trace = debug_backtrace();
    array_shift( $trace );

    while ( $skip > 0 ) {
      array_shift( $trace );
      $skip--;
    }

    return $trace;
  }
}

class FullErrorException extends ErrorException
{
  private $trace;

  private static $typeToName = array(
    E_ERROR             => "Error",
    E_RECOVERABLE_ERROR => "Recoverable error",
    E_WARNING           => "Warning",
    E_PARSE             => "Parse error",
    E_NOTICE            => "Notice",
    E_STRICT            => "Strict notice",
    E_DEPRECATED        => "Deprecated notice",
    E_CORE_ERROR        => "Core error",
    E_CORE_WARNING      => "Core warning",
    E_COMPILE_ERROR     => "Compile error",
    E_COMPILE_WARNING   => "Compile warning",
    E_USER_ERROR        => "User error",
    E_USER_WARNING      => "User warning",
    E_USER_NOTICE       => "User notice",
    E_USER_DEPRECATED   => "User deprecated notice",
  );

  public function __construct( $message, $type, $file, $line, array $trace )
  {
    $this->trace = $trace;

    if ( isset( self::$typeToName[$type] ) )
      $message = self::$typeToName[$type] . ": $message";

    parent::__construct( $message, $type, null, $file, $line );
  }

  public function getTraceWithObjects()
  {
    return $this->trace;
  }
}

class FailedAssertion extends Exception
{
  private $trace;

  public function __construct( $file, $line, $message, array $trace )
  {
    $this->trace = $trace;
    $this->file      = $file;
    $this->line      = $line;
    $this->code      = null;

    if ( $message !== '' )
      $this->message = "Assertion failed: $message";
    else
      $this->message = "Assertion failed";
  }

  public function getTraceWithObjects()
  {
    return $this->trace;
  }
}

function assertEqual( $actual, $expected )
{
  if ( $actual !== $expected ) {
    $actual   = PhpDump::dump( $actual );
    $expected = PhpDump::dump( $expected );

    throw new Exception( "Expected $expected, got $actual." );
  }
}

