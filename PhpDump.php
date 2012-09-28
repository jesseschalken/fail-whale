<?php

final class PhpDump
{
  private function __construct() {}

  public static function dumpException( Exception $e = null, $indent = '' )
  {
    if ( $e === null )
      return self::dumpShallow( $e );

    if ( $e instanceof FullErrorException || $e instanceof FailedAssertion )
      $trace = $e->getTraceWithObjects();
    else
      $trace = $e->getTrace();

    $code           = self::dump( $e->getCode() );
    $message        = self::dump( $e->getMessage() );
    $file           = self::dump( $e->getFile() );
    $line           = self::dump( $e->getLine() );
    $previous       = self::dumpException( $e->getPrevious(), "$indent  " );
    $trace          = self::dumpTrace( $trace, "$indent    " );
    $exceptionClass = get_class( $e );

    return <<<eot
$exceptionClass
$indent  code $code, message $message
$indent  in file $file, line $line
$indent  stack trace: $trace
$indent  previous exception: $previous
eot;
  }

  public static function dumpTrace( array $trace, $indent = '' )
  {
    $result = "";

    foreach ( $trace as $call ) {
      $file     = @$call['file'];
      $line     = @$call['line'];
      $class    = @$call['class'];
      $object   = @$call['object'];
      $type     = @$call['type'];
      $function = @$call['function'];
      $args     = @$call['args'];

      $file = self::dump( $file );
      $line = self::dump( $line );

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

    if ( isset( $args ) ) {
      foreach ( $args as &$arg )
        $arg = self::dump( $arg, "$indent  " );

      $args = join( ', ', $args );

      if ( $args === '' )
        $args = '()';
      else
        $args = "( $args )";
    } else
      $args = '';

    return "$object$type$function$args";
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
    $map = array(
      "\\"       => '\\\\',
      "\n"       => '\n',
      "\r"       => '\r',
      "\t"       => '\t',
      "\v"       => '\v',
      // "\e"       => '\e',
      "\f"       => '\f',
      "\$"       => '\$',
      "\""       => '\"',
      chr( 127 ) => '\x' . dechex( 127 ),
    );

    for ( $i = 0; $i < 32; $i++ )
      if ( !isset( $map[chr( $i )] ) )
        $map[chr( $i )] = '\x' . substr( '00' . dechex( $i ), -2 );

    return '"' . str_replace( array_keys( $map ), array_values( $map ), $string ) . '"';
  }

  private static function dumpFloat( $float )
  {
    if ( (string) (int) $float === (string) $float )
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

  private static function dumpArray( array &$array, $indent, array $context )
  {
    foreach ( $context as &$c )
      if ( is_array( $c ) && self::refsEqual( $c, $array ) )
        return self::dumpShallow( $array );

    $context[] =& $array;

    $entries = array();

    $isAssociative = self::isArrayAssociative( $array );

    foreach ( $array as $k => &$v ) {
      if ( $isAssociative )
        $keyPart = self::dumpDeep( $k, "$indent  ", $context ) . ' => ';
      else
        $keyPart = '';

      $entries[] = $keyPart . self::dumpDeep( $v, "$indent  ", $context );
    }

    if ( count( $entries ) == 0 )
      return 'array()';

    if ( count( $entries ) <= 3 ) {
      $joined = join( ', ', $entries );

      if ( strstr( $joined, "\n" ) === false )
        return "array( $joined )";
    }

    foreach ( $entries as &$value )
      $value = "$indent  $value,\n";

    $entries = join( '', $entries );

    return "array(\n$entries$indent)";
  }

  private static function refsEqual( &$a, &$b )
  {
    $aOld   = $a;
    $a      = (object) null;
    $result = $a === $b;
    $a      = $aOld;
    return $result;
  }

  private static function dumpObject( $object, $indent, array $context )
  {
    foreach ( $context as $c )
      if ( is_object( $c ) && $object === $c )
        return self::dumpShallow( $object );

    $objectValues = (array) $object;
    $classname    = get_class( $object );

    if ( empty( $objectValues ) )
      return "new $classname {}";

    $context[] = $object;

    $class      = new ReflectionClass( $object );
    $properties = '';
    $topClass   = true;

    do {
      foreach ( $class->getProperties() as $prop ) {
        if ( !$prop->isStatic() && ( $topClass || $prop->isPrivate() ) ) {
          $prop = self::dumpObjectProperty( $objectValues, $prop, "$indent  ", $context );

          $properties .= "$indent  $prop;\n";
        }
      }

      $topClass = false;
    } while ( $class = $class->getParentClass() );

    return "new $classname {\n$properties$indent}";
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

