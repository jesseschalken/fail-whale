<?php

final class PhpDump
{
  private static $errorConstants = array(
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

  private static function errorTypeString( $type )
  {
    foreach ( self::$errorConstants as $c )
      if ( @constant( $c ) === $type )
        return $c;

    return 'unknown';
  }

  public static function dumpError( $type, $message, $file, $line, $locals, $trace )
  {
    $errorType = self::errorTypeString( $type );
    $message   = self::dumpShallow( $message );
    $file      = self::dumpShallow( $file );
    $line      = self::dumpShallow( $line );

    $lines[] = "PHP error";
    $lines[] = "  type $errorType";
    $lines[] = "  message $message";
    $lines[] = "  in file $file, line $line";
    $lines[] = "";
    $lines[] = "stack trace:";

    foreach ( self::dumpTrace( $trace ) as $line )
      $lines[] = "  $line";

    $lines[] = "";
    $lines[] = "locals:";

    foreach ( self::dumpVariables( $locals ) as $line )
      $lines[] = "  $line";

    $lines[] = "";
    $lines[] = "globals:";

    foreach ( self::dumpVariables( $GLOBALS ) as $line )
      $lines[] = "  $line";

    return $lines;
  }

  private static function dumpVariables( array &$vars = null )
  {
    if ( $vars === null )
      return array( 'unavailable' );

    if ( empty( $vars ) )
      return array( 'none' );

    $varLines = array();

    foreach ( $vars as $k => &$v )
      $varLines[] = self::wrap( self::asVariableName( $k ) . " = ", self::dumpRef( $v, array( &$vars ) ), ';' );

    return self::addLineNumbers( self::groupLines( $varLines ) );
  }

  private static function groupLines( array $liness )
  {
    $lastWasMultiline = false;
    $resultLines      = array();

    foreach ( $liness as $k => $lines ) {
      $isMultiline = count( $lines ) > 1;

      if ( $k !== 0 )
        if ( $lastWasMultiline || $isMultiline )
          $resultLines[] = '';

      foreach ( $lines as $line )
        $resultLines[] = $line;

      $lastWasMultiline = $isMultiline;
    }

    return $resultLines;
  }

  public static function dumpException( Exception $e )
  {
    $lines = self::dumpExceptionBrief( $e );
    $lines[0] = "uncaught {$lines[0]}";
    $lines[] = "";
    $lines[] = "globals:";

    foreach ( self::dumpVariables( $GLOBALS ) as $line )
      $lines[] = "  $line";

    return $lines;
  }

  private static function dumpExceptionBrief( Exception $e = null )
  {
    if ( $e === null )
      return array( 'none' );

    $exceptionClass = get_class( $e );
    $code           = self::dumpShallow( $e->getCode() );
    $message        = self::dumpShallow( $e->getMessage() );
    $file           = self::dumpShallow( $e->getFile() );
    $line           = self::dumpShallow( $e->getLine() );

    $lines[] = "$exceptionClass";
    $lines[] = "  code $code";
    $lines[] = "  message $message";
    $lines[] = "  in file $file, line $line";
    $lines[] = "";
    $lines[] = "stack trace:";

    foreach ( self::dumpTrace( $e->getTrace() ) as $line )
      $lines[] = "  $line";

    $lines[] = "";
    $lines[] = "previous exception:";

    foreach ( self::dumpExceptionBrief( $e->getPrevious() ) as $line )
      $lines[] = "  $line";

    return $lines;
  }

  private static function dumpTrace( array $trace = null )
  {
    if ( $trace === null )
      return array( 'unavailable' );

    foreach ( $trace as $c ) {
      $file = self::dumpShallow( @$c['file'] );
      $line = self::dumpShallow( @$c['line'] );

      $lines[] = "- file $file, line $line";

      foreach ( self::addLineNumbers( self::dumpFunctionCall( $c ) ) as $k => $line )
        $lines[] = "    $line";

      $lines[] = '';
    }

    $lines[] = "- {main}";

    return $lines;
  }

  private static function addLineNumbers( array $lines )
  {
    $numDigits = max( mb_strlen( (string) count( $lines ) ), 3 );
    $space     = str_repeat( ' ', $numDigits );

    $i = 1;

    foreach ( $lines as &$line )
      $line = mb_substr( $i++ . $space, 0, $numDigits ) . " $line";

    return $lines;
  }

  private static function dumpFunctionCall( array $call )
  {
    if ( isset( $call['object'] ) )
      $object = self::dump( $call['object'] );
    else if ( isset( $call['class'] ) )
      $object = array( @$call['class'] );
    else
      $object = array();

    if ( isset( $call['args'] ) )
      $args = self::dumpFunctionArgs( $call['args'] );
    else
      $args = array( '( ? )' );

    return self::concat( array(
      $object,
      array( @$call['type'] . @$call['function'] ),
      $args,
      array( ';' )
    ) );
  }

  private static function dumpFunctionArgs( array $args )
  {
    if ( empty( $args ) )
      return array( '()' );

    $concat[] = array( '( ' );
    $concat[] = self::dump( array_shift( $args ) );

    foreach ( $args as $k => &$arg ) {
      $concat[] = array( ', ' );
      $concat[] = self::dump( $arg );
    }

    $concat[] = array( ' )' );

    return self::concat( $concat );
  }

  private static function asVariableName( $varname )
  {
    if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varname ) )
      return "\$$varname";
    else
      return "\${" . self::dumpString( $varname ) . "}";
  }

  public static function dump( $x )
  {
    return self::dumpRef( $x );
  }

  public static function dumpRef( &$x, array $arrayContext = array() )
  {
    $self = new self;

    foreach ( $arrayContext as &$a )
      $self->arrayContext[] =& $a;

    return $self->dumpRefLines( $x );
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

    for ( $i = 0; $i < mb_strlen( $string ); $i++ )
      $escaped .= self::escapeChar( mb_substr( $string, $i, 1 ) );

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
      return '\x' . mb_substr( '00' . dechex( $ord ), -2 );

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

  private static function concat( array $liness )
  {
    $result = array();
    $i      = 0;

    foreach ( $liness as $lines )
      foreach ( $lines as $k => $line )
        if ( $k == 0 && $i != 0 )
          $result[$i - 1] .= $line;
        else
          $result[$i++] = $line;

    return $result;
  }

  private static function wrap( $prepend, array $lines, $append )
  {
    return self::concat( array( array( $prepend ), $lines, array( $append ) ) );
  }

  private static function isArrayAssociative( array $array )
  {
    $i = 0;

    foreach ( $array as $k => $v )
      if ( $k !== $i++ )
        return true;

    return false;
  }

  private static function refsEqual( &$a, &$b )
  {
    $temp   = $a;
    $a      = (object) null;

    $result = $a === $b;
    $a      = $temp;

    return $result;
  }

  private $objectContext = array();
  private $arrayContext  = array();

  private function __construct()
  {
  }

  private function dumpRefLines( &$x )
  {
    $self = clone $this;

    if ( is_object( $x ) )
      return $self->dumpObjectLines( $x );
    else if ( is_array( $x ) )
      return $self->dumpArrayLines( $x );
    else
      return array( self::dumpShallow( $x ) );
  }

  private function dumpObjectPropertyLines( array $objectValues, ReflectionProperty $prop )
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

    $lines = $this->dumpRefLines( $objectValues[$key] );

    return self::wrap( "$access " . self::asVariableName( $name ) . " = ", $lines, ';' );
  }

  private function dumpObjectPropertiesLines( $object )
  {
    $propertiesLines = array();
    $objectValues  = (array) $object;
    $class         = new ReflectionClass( $object );
    $isObjectClass = true;

    do {
      foreach ( $class->getProperties() as $prop )
        if ( !$prop->isStatic() && ( $prop->isPrivate() || $isObjectClass ) )
          $propertiesLines[] = self::dumpObjectPropertyLines( $objectValues, $prop );

      $isObjectClass = false;
    } while ( $class = $class->getParentClass() );

    return self::groupLines( $propertiesLines );
  }

  private function dumpArrayEntriesLines( array $array )
  {
    $entriesLines = array();

    $isAssociative = self::isArrayAssociative( $array );

    foreach ( $array as $k => &$v ) {
      $keyPart = $isAssociative ? self::dumpShallow( $k ) . " => " : "";

      $entriesLines[] = self::wrap( $keyPart, $this->dumpRefLines( $v ), '' );
    }

    return $entriesLines;
  }

  private function dumpArrayDeep( array $array )
  {
    $entriesLines = $this->dumpArrayEntriesLines( $array );

    if ( count( $entriesLines ) == 0 )
      return array( 'array()' );
    else if ( count( $entriesLines ) <= 3 )
      return $this->dumpArrayOneline( $entriesLines );
    else
      return $this->dumpArrayMultiline( $entriesLines );
  }

  private function dumpArrayOneline( array $entriesLines )
  {
    $entries = array();

    foreach ( $entriesLines as $entryLines )
      if ( count( $entryLines ) <= 1 )
        $entries[] = join( '', $entryLines );
      else
        return $this->dumpArrayMultiline( $entriesLines );

    return array( "array( " . join( ', ', $entries ) . " )" );
  }

  private function dumpArrayMultiline( array $entriesLines )
  {
    $lines[] = 'array(';

    foreach ( $entriesLines as &$entryLines )
      $entryLines = self::wrap( '', $entryLines, ',' );

    foreach ( self::groupLines( $entriesLines ) as $line )
      $lines[] = "    $line";

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

    $propertyLines = $this->dumpObjectPropertiesLines( $object );

    if ( empty( $propertyLines ) )
      return array( "new $classname {}" );

    $lines[] = "new $classname {";

    foreach ( $propertyLines as $line )
      $lines[] = "    $line";

    $lines[] = '}';

    return $lines;
  }
}

