<?php

final class PhpExceptionDump
{
  private function __construct()
  {
  }

  public static function dumpExceptionWithTrace( Exception $e, array $trace, $newline = "\n" )
  {
    $exceptionClass = get_class( $e );
    $code           = self::dumpShallow( $e->getCode() );
    $message        = self::dumpShallow( $e->getMessage() );
    $file           = self::dumpShallow( $e->getFile() );
    $line           = self::dumpShallow( $e->getLine() );
    $trace          = self::dumpTrace( $trace, "$newline    " );
    $previous       = self::dumpPreviousException( $e->getPrevious(), "$newline  " );

    return join( $newline, array(
      "$exceptionClass",
      "  code $code, message $message",
      "  in file $file, line $line",
      "  stack trace:",
      "    $trace",
      "  previous exception: $previous",
    ) );
  }

  private static function dumpTrace( array $trace, $newline )
  {
    foreach ( $trace as $c ) {
      $file = self::dumpShallow( @$c['file'] );
      $line = self::dumpShallow( @$c['line'] );

      $functionCall = self::dumpFunctionCall( $c, "$newline  " );

      $lines[] = "- file $file, line $line";
      $lines[] = "  $functionCall;";
    }

    $lines[] = "- {main}";

    return join( $newline, $lines );
  }

  private static function dumpPreviousException( Exception $e = null, $newline )
  {
    if ( $e === null )
      return self::dumpShallow( $e );
    else
      return self::dumpExceptionWithTrace( $e, $e->getTrace(), $newline );
  }

  private static function dumpFunctionCall( array $call, $newline )
  {
    if ( isset( $call['object'] ) )
      $object = self::dump( $call['object'], $newline );
    else if ( isset( $call['class'] ) )
      $object = 'new ' . @$call['class'] . ' {…}';
    else
      $object = '';

    if ( isset( $call['args'] ) )
      $args = self::dumpFunctionArgs( $call['args'], $newline );
    else
      $args = '( ? )';

    return $object . @$call['type'] . @$call['function'] . $args;
  }

  private static function dumpFunctionArgs( array $args, $newline )
  {
    if ( empty( $args ) )
      return '()';

    foreach ( $args as &$arg )
      $arg = self::dump( $arg, "$newline  " );

    return "( " . join( ', ', $args ) . " )";
  }

  private static function dump( $x, $newline )
  {
    return PhpDump::dump( $x, $newline );
  }

  private static function dumpShallow( $x )
  {
    return PhpDump::dumpShallow( $x );
  }
}

final class PhpDump
{
  public static function dump( $x, $newline = "\n" )
  {
    return self::dumpRef( $x, $newline );
  }

  public static function dumpRef( &$x, $newline = "\n" )
  {
    $self = new self( $newline );

    return $self->_dumpRef( $x );
  }

  public static function dumpShallow( $x )
  {
    if ( is_int( $x ) )      return "$x";
    if ( is_float( $x ) )    return self::dumpFloat( $x );
    if ( is_null( $x ) )     return 'null';
    if ( is_bool( $x ) )     return $x ? 'true' : 'false';
    if ( is_resource( $x ) ) return get_resource_type( $x );
    if ( is_array( $x ) )    return empty( $x ) ? 'array()' : 'array(…)';
    if ( is_object( $x ) )   return 'new ' . get_class( $x ) . ' {…}';
    if ( is_string( $x ) )   return self::dumpString( $x );

    return 'unknown type';
  }

  private static function dumpString( $string )
  {
    $escaped = '';

    for ( $i = 0; $i < strlen( $string ); $i++ )
      $escaped .= self::escapeChar( $string[$i] );

    return "\"$escaped\"";
  }

  private static function escapeChar( $char )
  {
    $map = array(
      "\\" => '\\\\',
      "\n" => '\n',
      "\r" => '\r',
      "\t" => '\t',
      "\v" => '\v',
      "\f" => '\f',
      "\$" => '\$',
      "\"" => '\"',
    );

    if ( isset( $map[$char] ) )
      return $map[$char];

    $ord = ord( $char );

    if ( ( $ord >= 0 && $ord < 32 ) || $ord === 127 )
      return '\x' . substr( '00' . dechex( $ord ), -2 );

    return $char;
  }

  private static function dumpFloat( $float )
  {
    $int = (int) $float;

    if ( "$int" === "$float" )
      return "$float.0";
    else
      return "$float";
  }

  private $newline;
  private $objectContext = array();
  private $arrayContext  = array();

  private function __construct( $newline )
  {
    $this->newline = $newline;
  }

  private function _dumpRef( &$x )
  {
    $self = clone $this;
    $self->newline .= "  ";

    if ( is_object( $x ) )
      $lines = $self->dumpObjectLines( $x );
    else if ( is_array( $x ) )
      $lines = $self->dumpArrayLines( $x );
    else
      $lines = array( self::dumpShallow( $x ) );

    return join( $this->newline, $lines );
  }

  private function dumpObjectProperty( array $object, ReflectionProperty $prop )
  {
    $name = $prop->getName();

    if ( $prop->isPrivate() )
      list( $access, $key ) = array( 'private', "\x00" . $prop->getDeclaringClass()->getName() . "\x00$name" );
    else if ( $prop->isProtected() )
      list( $access, $key ) = array( 'protected', "\x00*\x00$name" );
    else if ( $prop->isPublic() )
      list( $access, $key ) = array( 'public', "$name" );
    else
      assert( false );

    return "$access \$$name = " . $this->_dumpRef( $object[$key] );
  }

  private function dumpObjectProperties( $o )
  {
    $properties    = array();
    $object        = (array) $o;
    $class         = new ReflectionClass( $o );
    $isObjectClass = true;

    do {
      foreach ( $class->getProperties() as $prop )
        if ( !$prop->isStatic() && ( $prop->isPrivate() || $isObjectClass ) )
          $properties[] = self::dumpObjectProperty( $object, $prop );

      $isObjectClass = false;
    } while ( $class = $class->getParentClass() );

    return $properties;
  }

  private static function isArrayAssociative( array $array )
  {
    $i = 0;

    foreach ( $array as $k => $v )
      if ( $k !== $i++ )
        return true;

    return false;
  }

  private function dumpArrayEntries( array $array )
  {
    $entries = array();

    $isAssociative = self::isArrayAssociative( $array );

    foreach ( $array as $k => &$v )
      if ( $isAssociative )
        $entries[] = $this->_dumpRef( $k ) . " => " . $this->_dumpRef( $v );
      else
        $entries[] = $this->_dumpRef( $v );

    return $entries;
  }

  private function dumpArrayDeep( array $array )
  {
    $entries = $this->dumpArrayEntries( $array );

    if ( count( $entries ) == 0 )
      return array( 'array()' );
    else if ( count( $entries ) <= 3 )
      return $this->dumpArrayOneline( $entries );
    else
      return $this->dumpArrayMultiline( $entries );
  }

  private function dumpArrayOneline( array $entries )
  {
    $result = "array( " . join( ', ', $entries ) . " )";

    if ( strstr( $result, "\n" ) === false )
      return array( $result );
    else
      return self::dumpArrayMultiline( $entries );
  }

  private function dumpArrayMultiline( array $entries )
  {
    $lines[] = 'array(';

    foreach ( $entries as $entry )
      $lines[] .= "  $entry,";

    $lines[] = ')';

    return $lines;
  }

  private function dumpArrayLines( array &$array )
  {
    foreach ( $this->arrayContext as &$c )
      if ( self::refsEqual( $c, $array ) )
        return array( 'array( *recursion* )' );

    $this->arrayContext[] =& $array;

    return $this->dumpArrayDeep( $array );
  }

  private static function refsEqual( &$a, &$b )
  {
    $temp   = $a;
    $a      = (object) null;

    $result = $a === $b;
    $a      = $temp;

    return $result;
  }

  private function dumpObjectLines( $object )
  {
    foreach ( $this->objectContext as $c )
      if ( $object === $c )
        return array( 'new ' . get_class( $object ) . ' { *recursion* }' );

    $this->objectContext[] = $object;

    return $this->dumpObjectLinesDeep( $object );
  }

  private function dumpObjectLinesDeep( $object )
  {
    $classname = get_class( $object );

    $properties = $this->dumpObjectProperties( $object );

    if ( empty( $properties ) )
      return array( "new $classname {}" );

    $lines[] = "new $classname {";

    foreach ( $properties as $prop )
      $lines[] = "  $prop;";

    $lines[] = '}';

    return $lines;
  }
}

