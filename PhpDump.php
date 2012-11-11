<?php

final class PhpDump
{
	public static function dumpExceptionLines( Exception $e )
	{
		$lines    = self::dumpExceptionBrief( $e );
		$lines[0] = "uncaught {$lines[0]}";
		$lines[]  = "";
		$lines[]  = "global variables:";

		foreach ( self::dumpVariables( $GLOBALS ) as $line )
			$lines[] = "  $line";

		return $lines;
	}

	public static function dumpExceptionOneLine( Exception $e )
	{
		$exceptionClass = get_class( $e );
		$code           = (string) $e->getCode();
		$message        = self::dumpShallow( $e->getMessage() );
		$file           = self::dumpShallow( $e->getFile() );
		$line           = self::dumpShallow( $e->getLine() );

		return "uncaught $exceptionClass, code $code, message $message in file $file, line $line";
	}

	private static function dumpExceptionBrief( Exception $e )
	{
		$exceptionClass = get_class( $e );
		$code           = (string) $e->getCode();
		$message        = self::dumpShallow( $e->getMessage() );
		$file           = self::dumpShallow( $e->getFile() );
		$line           = self::dumpShallow( $e->getLine() );

		$trace = $e instanceof PhpErrorException ? $e->getFullTrace() : $e->getTrace();

		$lines[] = "$exceptionClass";
		$lines[] = "  code $code, message $message";
		$lines[] = "  file $file, line $line";
		$lines[] = "";
		$lines[] = "stack trace:";

		foreach ( self::dumpTrace( $trace ) as $line )
			$lines[] = "  $line";

		if ( $e instanceof PhpErrorException )
		{
			$context = $e->getContext();

			$lines[] = "";
			$lines[] = "local variables:";

			foreach ( self::dumpVariables( $context ) as $line )
				$lines[] = "  $line";
		}

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null )
		{
			$lines[] = "";
			$lines[] = "previous exception:";

			foreach ( self::dumpExceptionBrief( $e->getPrevious() ) as $line )
				$lines[] = "  $line";
		}

		return $lines;
	}

	private static function dumpTrace( array $trace = null )
	{
		foreach ( $trace as $c )
		{
			$file = self::dumpShallow( @$c['file'] );
			$line = self::dumpShallow( @$c['line'] );

			$lines[] = "- file $file, line $line";

			foreach ( self::addLineNumbers( self::dumpFunctionCall( $c ) ) as $line )
				$lines[] = "    $line";

			$lines[] = '';
		}

		$lines[] = "- {main}";

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

	private static function groupLines( array $lineGroups )
	{
		$lastWasMultiLine = false;
		$resultLines      = array();

		foreach ( $lineGroups as $k => $lines )
		{
			$isMultiLine = count( $lines ) > 1;

			if ( $k !== 0 )
				if ( $lastWasMultiLine || $isMultiLine )
					$resultLines[] = '';

			foreach ( $lines as $line )
				$resultLines[] = $line;

			$lastWasMultiLine = $isMultiLine;
		}

		return $resultLines;
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

		return self::concatenate( array(
		                               $object,
		                               array( @$call['type'] . @$call['function'] ),
		                               $args,
		                               array( ';' ),
		                          ) );
	}

	private static function dumpFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return array( '()' );

		$lineGroups[] = array( '( ' );
		$lineGroups[] = self::dump( array_shift( $args ) );

		foreach ( $args as &$arg )
		{
			$lineGroups[] = array( ', ' );
			$lineGroups[] = self::dump( $arg );
		}

		$lineGroups[] = array( ' )' );

		return self::concatenate( $lineGroups );
	}

	private static function asVariableName( $varName )
	{
		if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varName ) )
			return "\$$varName";
		else
			return "\${" . self::dumpString( $varName ) . "}";
	}

	public static function dump( $x )
	{
		return self::dumpRef( $x );
	}

	private static function dumpRef( &$x, array $arrayContext = array() )
	{
		$self = new self;

		foreach ( $arrayContext as &$a )
			$self->arrayContext[] =& $a;

		return $self->dumpRefLines( $x );
	}

	public static function dumpShallow( $x )
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
			return empty( $x ) ? 'array()' : 'array(…)';

		if ( is_object( $x ) )
			return 'new ' . get_class( $x ) . ' {…}';

		if ( is_string( $x ) )
			return self::dumpString( $x );

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

		return "$int" === "$float" ? "$float.0" : "$float";
	}

	private static function concatenate( array $lineGroups )
	{
		$result = array();
		$i      = 0;

		foreach ( $lineGroups as $lines )
			foreach ( $lines as $k => $line )
				if ( $k == 0 && $i != 0 )
					$result[$i - 1] .= $line;
				else
					$result[$i++] = $line;

		return $result;
	}

	private static function wrap( $prepend, array $lines, $append )
	{
		return self::concatenate( array( array( $prepend ), $lines, array( $append ) ) );
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
		$temp = $a;
		$a    = (object) null;

		$result = $a === $b;
		$a      = $temp;

		return $result;
	}

	private $objectContext = array();
	private $arrayContext = array();

	private function __construct()
	{
	}

	private function dumpRefLines( &$x )
	{
		$self = clone $this;

		if ( is_object( $x ) )
			return $self->dumpObjectLines( $x );

		if ( is_array( $x ) )
			return $self->dumpArrayLines( $x );

		return array( self::dumpShallow( $x ) );
	}

	private function dumpObjectPropertiesLines( $object )
	{
		$propertiesLines = array();

		$objectProperties = (array) $object;

		foreach ( $objectProperties as $k => &$propertyValue )
		{
			@list( $empty, $className, $propertyName ) = explode( "\x00", $k );

			$access = 'public';

			if ( $empty === '' )
				$access = $className === '*' ? 'protected' : 'private';
			else
				$propertyName = $empty;

			$propertiesLines[] = self::wrap( "$access " . self::asVariableName( $propertyName ) . " = ",
			                                 $this->dumpRefLines( $propertyValue ),
			                                 ';' );
		}

		return self::groupLines( $propertiesLines );
	}

	private function dumpArrayEntriesLines( array $array )
	{
		$entriesLines = array();

		$isAssociative = self::isArrayAssociative( $array );

		foreach ( $array as $k => &$v )
		{
			$keyPart = $isAssociative ? self::dumpShallow( $k ) . " => " : "";

			$entriesLines[] = self::wrap( $keyPart, $this->dumpRefLines( $v ), '' );
		}

		return $entriesLines;
	}

	private function dumpArrayDeep( array $array )
	{
		$entriesLines = $this->dumpArrayEntriesLines( $array );

		if ( empty( $entriesLines ) )
			return array( 'array()' );

		$totalSize = 0;

		foreach ( $entriesLines as $entryLines )
			foreach ( $entryLines as $line )
				$totalSize += mb_strlen( $line );

		if ( $totalSize <= 32 )
			return $this->dumpArrayOneLine( $entriesLines );
		else
			return $this->dumpArrayMultiLine( $entriesLines );
	}

	private function dumpArrayOneLine( array $entriesLines )
	{
		$entries = array();

		foreach ( $entriesLines as $entryLines )
			if ( count( $entryLines ) <= 1 )
				$entries[] = join( '', $entryLines );
			else
				return $this->dumpArrayMultiLine( $entriesLines );

		return array( "array( " . join( ', ', $entries ) . " )" );
	}

	private function dumpArrayMultiLine( array $entriesLines )
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

		/**
		 * In PHP 5.2.4, this class was not able to detect the recursion of the
		 * following structure, resulting in a stack overflow.
		 *
		 *   $a         = new stdClass;
		 *   $a->b      = array();
		 *   $a->b['c'] =& $a->b;
		 *
		 * But PHP 5.3.17 was able. The exact reason I am not sure, but I will enforce
		 * a maximum depth limit for PHP versions older than the earliest for which I
		 * know the recursion detection works.
		 */
		if ( PHP_VERSION_ID < 50317 && count( $this->arrayContext ) > 10 )
			return array( 'array( *maximum depth exceeded* )' );

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
		$className = get_class( $object );

		$propertyLines = $this->dumpObjectPropertiesLines( $object );

		if ( empty( $propertyLines ) )
			return array( "new $className {}" );

		$lines[] = "new $className {";

		foreach ( $propertyLines as $line )
			$lines[] = "    $line";

		$lines[] = '}';

		return $lines;
	}
}
