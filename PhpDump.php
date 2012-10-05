<?php

final class PhpDump
{
  private function __construct() {}

  public static function dumpException( Exception $e, $indent )
  {
    return self::dumpExceptionWithTrace( $e, $e->getTrace(), $indent );
  }

  public static function dumpExceptionWithTrace( Exception $e, array $trace, $indent )
  {
    $exceptionClass = get_class( $e );
    $code           = self::dump( $e->getCode() );
    $message        = self::dump( $e->getMessage() );
    $file           = self::dump( $e->getFile() );
    $line           = self::dump( $e->getLine() );
    $trace          = self::dumpTrace( $trace, "$indent    " );
    $previous       = self::dumpPreviousException( $e->getPrevious(), "$indent  " );

    return <<<eot
$exceptionClass
$indent  code $code, message $message
$indent  in file $file, line $line
$indent  stack trace: $trace
$indent  previous exception: $previous
eot;
  }

  private static function dumpPreviousException( Exception $e = null, $indent )
  {
    if ( $e === null )
      return self::dump( $e );
    else
      return self::dumpException( $e, $indent );
  }

  public static function dumpTrace( array $trace, $indent )
  {
    $result = "";

    foreach ( $trace as $c ) {
      $class    = @$c['class'];
      $object   = @$c['object'];
      $type     = @$c['type'];
      $function = @$c['function'];
      $args     = @$c['args'];

      $file = self::dump( @$c['file'] );
      $line = self::dump( @$c['line'] );

      $functionCall = self::dumpFunctionCall( $class, $object, $type, $function, $args, "$indent  " );

      $result .= "\n{$indent}file $file, line $line";
      $result .= "\n{$indent}  $functionCall;";
    }

    $result .= "\n$indent{main}";

    return $result;
  }

  private static function dumpFunctionCall( $class, $object, $type, $function, $args, $indent )
  {
    if ( isset( $object ) )
      $object = self::dump( $object, $indent );
    else
      $object = $class;

    if ( isset( $args ) )
      $args = self::dumpFunctionArgs( $args, $indent );
    else
      $args = '';

    return "$object$type$function$args";
  }

  private static function dumpFunctionArgs( array $args, $indent )
  {
    if ( empty( $args ) )
      return '()';

    foreach ( $args as &$arg )
      $arg = self::dump( $arg, "$indent  " );

    return "( " . join( ', ', $args ) . " )";
  }

  public static function dump( $x, $indent = '' )
  {
    return self::dumpRef( $x, $indent );
  }

  public static function dumpRef( &$x, $indent = '' )
  {
    return self::dumpDeep( $x, $indent, array() );
  }

  private static function dumpDeep( &$x, $indent, array $context )
  {
    if ( is_object( $x ) )
      return self::dumpObject( $x, $indent, $context );

    if ( is_array( $x ) )
      return self::dumpArray( $x, $indent, $context );

    return self::dumpShallow( $x );
  }

  private static function dumpShallow( $x )
  {
    if ( is_int( $x ) )
      return "$x";

    if ( is_float( $x ) )
      return self::dumpFloat( $x );

    if ( is_null( $x ) )
      return 'null';

    if ( is_bool( $x ) )
      return $x ? 'true' : 'false';

    if ( is_resource( $x ) )
      return get_resource_type( $x );

    if ( is_array( $x ) )
      return 'array(…)';

    if ( is_object( $x ) )
      return 'new ' . get_class( $x ) . ' {…}';

    if ( is_string( $x ) )
      return self::dumpString( $x );

    return 'unknown type';
  }

  private static function dumpString( $string )
  {
    $result = '"';

    $length = strlen( $string );

    for ( $i = 0; $i < $length; $i++ )
      $result .= self::escapeChar( $string[$i] );

    $result .= '"';

    if ( $result === "\"$string\"" )
      return "'$string'";
    else
      return $result;
  }

  private static function escapeChar( $char )
  {
    $map = array(
      "\\"       => '\\\\',
      "\n"       => '\n',
      "\r"       => '\r',
      "\t"       => '\t',
      "\v"       => '\v',
      // TODO in PHP 5.3
      // "\e"       => '\e',
      "\f"       => '\f',
      "\$"       => '\$',
      "\""       => '\"',
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

  private static function isArrayAssociative( array $array )
  {
    $i = 0;

    foreach ( $array as $k => $v )
      if ( $k !== $i++ )
        return true;

    return false;
  }

  private static function dumpArrayEntries( array $array, $indent, array $context )
  {
    $entries = array();

    $isAssociative = self::isArrayAssociative( $array );

    foreach ( $array as $k => &$v ) {
      $value = self::dumpDeep( $v, "$indent  ", $context );

      if ( $isAssociative )
        $entries[] = self::dumpDeep( $k, "$indent  ", $context ) . " => $value";
      else
        $entries[] = $value;
    }

    return $entries;
  }

  private static function dumpArrayDeep( array $array, $indent, array $context )
  {
    $entries = self::dumpArrayEntries( $array, $indent, $context );

    if ( count( $entries ) == 0 )
      return 'array()';
    else if ( count( $entries ) <= 3 )
      return self::dumpArrayOneline( $entries, $indent );
    else
      return self::dumpArrayMultiline( $entries, $indent );
  }

  private static function dumpArrayOneline( array $entries, $indent )
  {
    $result = "array( " . join( ', ', $entries ) . " )";

    if ( strstr( $result, "\n" ) === false )
      return $result;
    else
      return self::dumpArrayMultiline( $entries, $indent );
  }

  private static function dumpArrayMultiline( array $entries, $indent )
  {
    $result = "array(\n";

    foreach ( $entries as $value )
      $result .= "$indent  $value,\n";

    $result .= "$indent)";

    return $result;
  }

  private static function dumpArray( array &$array, $indent, array $context )
  {
    foreach ( $context as &$c )
      if ( is_array( $c ) && self::refsEqual( $c, $array ) )
        return self::dumpShallow( $array );

    $context[] =& $array;

    return self::dumpArrayDeep( $array, $indent, $context );
  }

  private static function refsEqual( &$a, &$b )
  {
    $temp   = $a;
    $a      = (object) null;

    $result = $a === $b;
    $a      = $temp;

    return $result;
  }

  private static function dumpObject( $object, $indent, array $context )
  {
    foreach ( $context as $c )
      if ( is_object( $c ) && $object === $c )
        return self::dumpShallow( $object );

    $context[] = $object;

    return self::dumpObjectDeep( $object, $indent, $context );
  }

  private static function dumpObjectDeep( $object, $indent, array $context )
  {
    $classname = get_class( $object );

    $properties = self::dumpObjectProperties( $object, $indent, $context );

    if ( empty( $properties ) )
      return "new $classname {}";
    else
      return self::dumpObjectMultiline( $classname, $properties, $indent );
  }

  private static function dumpObjectMultiline( $classname, array $properties, $indent )
  {
    $result = "new $classname {\n";

    foreach ( $properties as $prop )
      $result .= "$indent  $prop;\n";

    $result .= "$indent}";

    return $result;
  }

  private static function dumpObjectProperties( $object, $indent, array $context )
  {
    $properties   = array();
    $objectValues = (array) $object;
    $class        = new ReflectionClass( $object );
    $derived      = false;

    do {
      foreach ( $class->getProperties() as $prop )
        if ( !$prop->isStatic() && ( $prop->isPrivate() || !$derived ) )
          $properties[] = self::dumpObjectProperty( $objectValues, $prop, "$indent  ", $context );

      $derived = true;
    } while ( $class = $class->getParentClass() );

    return $properties;
  }

  private static function dumpObjectProperty( array $objectValues, ReflectionProperty $prop, $indent, $context )
  {
    $name      = $prop->getName();
    $classname = $prop->getDeclaringClass()->getName();

    if ( $prop->isPrivate() ) {
      $access = 'private';
      $key    = "\x00$classname\x00$name";
    } else if ( $prop->isProtected() ) {
      $access = 'protected';
      $key    = "\x00*\x00$name";
    } else if ( $prop->isPublic() ) {
      $access = 'public';
      $key    = "$name";
    } else
      assert( false );

    $value = self::dumpDeep( $objectValues[$key], $indent, $context );

    return "$access \$$name = $value";
  }
}

