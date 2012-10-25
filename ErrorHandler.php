<?php

class ErrorHandler
{
  private $lastHandledError = null;

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
    assert_options( ASSERT_CALLBACK   , array( $this, 'phpHandleFailedAssertion' ) );

    set_error_handler          ( array( $this, 'phpHandleError'             ) );
    set_exception_handler      ( array( $this, 'phpHandleUncaughtException' ) );
    register_shutdown_function ( array( $this, 'phpHandleShutdown'          ) );

    $this->lastHandledError = error_get_last();
  }

  public final function phpHandleFailedAssertion( $file, $line, $message )
  {
    throw new AssertionFailedException( $file, $line, $message );
  }

  private function isUserError( $type )
  {
    $constants = array(
      'E_USER_ERROR',
      'E_USER_WARNING',
      'E_USER_NOTICE',
      'E_USER_DEPRECATED',
    );

    foreach ( $constants as $c )
      if ( @constant( $c ) === $type )
        return true;

    return false;
  }

  public final function phpHandleError( $type, $message, $file, $line, $localVariables = null )
  {
    if ( error_reporting() & $type ) {
      if ( $this->isUserError( $type ) )
        throw new ErrorException( $message, $type, null, $file, $line );

      $trace = debug_backtrace();
      array_shift( $trace );

      $this->handleError( $type, $message, $file, $line, $localVariables, $trace );

      $this->lastHandledError = error_get_last();
      exit( 1 );
    }

    $this->lastHandledError = error_get_last();
  }

  public final function phpHandleUncaughtException( Exception $e )
  {
    return $this->handleException( $e );
  }

  public final function phpHandleShutdown()
  {
    $e = error_get_last();

    if ( $e !== null && $e !== $this->lastHandledError ) {
      $trace = debug_backtrace();
      array_shift( $trace );

      $this->handleError( $e['type'], $e['message'], $e['file'], $e['line'], null, $trace );
    }
  }

  protected function handleError( $type, $message, $file, $line, $localVariables, $trace )
  {
    $this->send( join( "\n", PhpDump::dumpError( $type, $message, $file, $line, $localVariables, $trace ) ) . "\n" );
  }

  protected function handleException( Exception $e )
  {
    $this->send( join( "\n", PhpDump::dumpException( $e ) ) . "\n" );
  }

  private function send( $text )
  {
    while ( ob_get_level() > 0 )
      ob_end_clean();

    if ( PHP_SAPI === 'cli' )
      print $text;
    else
      $this->printHtml( $text );
  }

  private function toHtml( $text )
  {
    return join( "\n", array(
      "<div css=\"",
      "  white-space: pre;",
      "  font-family: 'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace;",
      "  font-size: 10pt;",
      "  color: black;",
      "  display: block;",
      "  background: white;",
      "  border: none;",
      "  margin: 0;",
      "  padding: 0;",
      "  line-height: 16px;",
      "\">" . htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) . "</div>",
    ) );
  }

  private function printHtml( $text )
  {
    @header( 'HTTP/1.1 500 Internal Server Error', true, 500 );
    @header( "Content-Type: text/html; charset=UTF-8", true );

    print $this->toHtml( $text );
  }
}

class AssertionFailedException extends Exception
{
  public function __construct( $file, $line, $message )
  {
    if ( $message !== '' )
      $message = "Assertion failed";
    else
      $message = "Assertion failed: $message";

    parent::__construct( $message );

    $this->file = $file;
    $this->line = $line;
  }
}

