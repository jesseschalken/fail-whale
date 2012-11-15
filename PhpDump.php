<?php

abstract class PhpDumper
{
	private $dumper;

	public function __construct( PhpCompositeDumper $dumper )
	{
		$this->dumper = $dumper;
	}

	/**
	 * @param $value
	 *
	 * @return string[]
	 */
	public abstract function dump( &$value );

	protected final function dumpRef( &$value )
	{
		return $this->dumper->dump( $value );
	}

	protected final function dumpValue( $value )
	{
		return $this->dumper->dump( $value );
	}

	protected final function dumpVariable( $varName )
	{
		return $this->dumper->_dumpVariable( $varName );
	}

	protected final function dumpOneLine( $value )
	{
		return join( '', $this->dumpValue( $value ) );
	}

	protected static function concatenate( array $lineGroups )
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

	protected static function groupLines( array $lineGroups )
	{
		$lastWasMultiLine = false;
		$resultLines      = array();

		foreach ( $lineGroups as $k => $lines )
		{
			$isMultiLine = count( self::splitNewLines( $lines ) ) > 1;

			if ( $k !== 0 )
				if ( $lastWasMultiLine || $isMultiLine )
					$resultLines[] = '';

			foreach ( $lines as $line )
				$resultLines[] = $line;

			$lastWasMultiLine = $isMultiLine;
		}

		return $resultLines;
	}

	protected static function splitNewLines( array $lines )
	{
		return explode( "\n", join( "\n", $lines ) );
	}

	protected static function addLineNumbers( array $lines )
	{
		foreach ( $lines as $k => &$line )
			$line = substr( ( $k + 1 ) . '    ', 0, 4 ) . " $line";

		return $lines;
	}
}

abstract class PhpCachingDumper extends PhpDumper
{
	private $cache = array();

	public final function dump( &$value )
	{
		$serialized = $this->valueToString( $value );

		if ( !isset( $this->cache[$serialized] ) )
			$this->cache[$serialized] = $this->cacheMiss( $value );

		return $this->cache[$serialized];
	}

	protected abstract function valueToString( $value );

	protected abstract function cacheMiss( $value );
}

final class PhpStringDumper extends PhpCachingDumper
{
	protected function cacheMiss( $string )
	{
		$escaped = '';

		$length = strlen( $string );

		for ( $i = 0; $i < $length; $i++ )
			$escaped .= self::escapeChar( $string[$i] );

		return array( "\"$escaped\"" );
	}

	private static $escapeMap = array(
		"\\" => '\\\\',
		"\n" => "\n",
		"\r" => '\r',
		"\t" => "\t",
		"\v" => '\v',
		"\f" => '\f',
		"\$" => '\$',
		"\"" => '\"',
	);

	private static function escapeChar( $char )
	{
		if ( isset( self::$escapeMap[$char] ) )
			return self::$escapeMap[$char];

		$ord = ord( $char );

		if ( $ord >= 32 && $ord < 127 )
			return $char;
		else
			return '\x' . substr( '00' . dechex( $ord ), -2 );
	}

	protected function valueToString( $value )
	{
		return $value;
	}
}

final class PhpBooleanDumper extends PhpDumper
{
	public function dump( &$value )
	{
		return array( $value ? 'true' : 'false' );
	}
}

final class PhpIntegerDumper extends PhpDumper
{
	public function dump( &$int )
	{
		return array( "$int" );
	}
}

final class PhpFloatDumper extends PhpCachingDumper
{
	protected function cacheMiss( $float )
	{
		$int = (int) $float;

		return array( "$int" === "$float" ? "$float.0" : "$float" );
	}

	protected function valueToString( $value )
	{
		return (string) $value;
	}
}

final class PhpResourceDumper extends PhpDumper
{
	public function dump( &$resource )
	{
		return array( get_resource_type( $resource ) );
	}
}

final class PhpArrayDumper extends PhpDumper
{
	private $arrayContext = array();

	public function dump( &$array )
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

		$result = $this->dumpArrayDeep( $array );

		array_pop( $this->arrayContext );

		return $result;
	}

	private function dumpArrayEntriesLines( array $array )
	{
		$entriesLines = array();

		$isAssociative = self::isArrayAssociative( $array );

		foreach ( $array as $k => &$v )
		{
			$parts = array();

			if ( $isAssociative )
			{
				$parts[] = $this->dumpValue( $k );
				$parts[] = array( " => " );
			}

			$parts[] = $this->dumpRef( $v );

			$entriesLines[] = self::concatenate( $parts );
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
				$totalSize += strlen( $line );

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
			$entryLines = self::concatenate( array( $entryLines, array( ',' ) ) );

		foreach ( self::groupLines( $entriesLines ) as $line )
			$lines[] = "    $line";

		$lines[] = ')';

		return $lines;
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
		$a    = new stdClass;

		$result = $a === $b;
		$a      = $temp;

		return $result;
	}
}

final class PhpObjectDumper extends PhpCachingDumper
{
	private $objectContext = array();

	protected function cacheMiss( $object )
	{
		$hash = $this->valueToString( $object );

		if ( isset( $this->objectContext[$hash] ) )
			return array( 'new ' . get_class( $object ) . ' { *recursion* }' );

		$this->objectContext[$hash] = true;

		$result = $this->dumpObjectLinesDeep( $object );

		unset( $this->objectContext[$hash] );

		return $result;
	}

	protected function valueToString( $object )
	{
		return spl_object_hash( $object );
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

			$propertiesLines[] = self::concatenate( array(
			                                             array( "$access " ),
			                                             $this->dumpVariable( $propertyName ),
			                                             array( ' = ' ),
			                                             $this->dumpRef( $propertyValue ),
			                                             array( ';' )
			                                        ) );
		}

		return self::groupLines( $propertiesLines );
	}
}

final class PhpNullDumper extends PhpDumper
{
	public function dump( &$null )
	{
		return array( 'null' );
	}
}

final class PhpUnknownDumper extends PhpDumper
{
	public function dump( &$unknown )
	{
		return array( 'unknown type' );
	}
}

final class PhpCompositeDumper extends PhpDumper
{
	/** @var PhpDumper[] */
	private $dumpers = array();
	private $variableDumper;

	public function __construct()
	{
		$this->dumpers = array(
			'boolean'      => new PhpBooleanDumper( $this ),
			'integer'      => new PhpIntegerDumper( $this ),
			'double'       => new PhpFloatDumper( $this ),
			'string'       => new PhpStringDumper( $this ),
			'array'        => new PhpArrayDumper( $this ),
			'object'       => new PhpObjectDumper( $this ),
			'resource'     => new PhpResourceDumper( $this ),
			'NULL'         => new PhpNullDumper( $this ),
			'unknown type' => new PhpUnknownDumper( $this ),
		);

		$this->variableDumper = new PhpVariableDumper( $this );

		parent::__construct( $this );
	}

	public final function dump( &$value )
	{
		return $this->dumpers[gettype( $value )]->dump( $value );
	}

	public final function _dumpVariable( $varName )
	{
		return $this->variableDumper->dump( $varName );
	}
}

final class PhpVariableDumper extends PhpCachingDumper
{
	protected function valueToString( $value )
	{
		return $value;
	}

	protected function cacheMiss( $varName )
	{
		if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varName ) )
			return array( "\$$varName" );
		else
			return self::concatenate( array(
			                               array( '${' ),
			                               $this->dumpValue( $varName ),
			                               array( '}' ),
			                          ) );
	}
}

final class PhpExceptionDumper extends PhpDumper
{
	/**
	 * @param Exception $exception
	 *
	 * @return string[]
	 */
	public function dump( &$exception )
	{
		$lines    = $this->dumpExceptionBrief( $exception );
		$lines[0] = "uncaught {$lines[0]}";
		$lines[]  = "";
		$lines[]  = "global variables:";

		foreach ( $this->dumpVariables( self::globals() ) as $line )
			$lines[] = "  $line";

		return $lines;
	}

	private static function globals()
	{
		/**
		 * Don't ask me why, but if I don't send $GLOBALS through array_merge(), unset( $globals['GLOBALS'] ) (next
		 * line) ends up removing the $GLOBALS superglobal itself.
		 */
		$globals = array_merge( $GLOBALS );
		unset( $globals['GLOBALS'] );

		return $globals;
	}

	public static function dumpExceptionOneLine( Exception $e )
	{
		$self = new self( new PhpCompositeDumper );

		$exceptionClass = get_class( $e );
		$code           = (string) $e->getCode();
		$message        = $self->dumpOneLine( $e->getMessage() );
		$file           = $self->dumpOneLine( $e->getFile() );
		$line           = $self->dumpOneLine( $e->getLine() );

		return "uncaught $exceptionClass, code $code, message $message in file $file, line $line";
	}

	public static function dumpException( Exception $e )
	{
		$self   = new self( new PhpCompositeDumper );
		$result = '';

		foreach ( $self->dump( $e ) as $line )
			$result .= "$line\n";

		return $result;
	}

	private function dumpExceptionBrief( Exception $e )
	{
		$exceptionClass = get_class( $e );
		$code           = (string) $e->getCode();
		$message        = $this->dumpOneLine( $e->getMessage() );
		$file           = $this->dumpOneLine( $e->getFile() );
		$line           = $this->dumpOneLine( $e->getLine() );

		$lines[] = "$exceptionClass";
		$lines[] = "  code $code";
		$lines[] = "  message $message";
		$lines[] = "  file $file";
		$lines[] = "  line $line";

		if ( $e instanceof PhpErrorException )
		{
			$lines[] = "";
			$lines[] = "local variables:";

			foreach ( $this->dumpVariables( $e->getContext() ) as $line )
				$lines[] = "  $line";
		}

		$lines[] = "";
		$lines[] = "stack trace:";

		foreach ( $this->dumpTrace( $e instanceof PhpErrorException ? $e->getFullTrace() : $e->getTrace() ) as $line )
			$lines[] = "  $line";

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null )
		{
			$lines[] = "";
			$lines[] = "previous exception:";

			foreach ( $this->dumpExceptionBrief( $e->getPrevious() ) as $line )
				$lines[] = "  $line";
		}

		return $lines;
	}

	private function dumpTrace( array $trace = null )
	{
		foreach ( $trace as $c )
		{
			$file = $this->dumpOneLine( @$c['file'] );
			$line = $this->dumpOneLine( @$c['line'] );

			$lines[] = "- file $file, line $line";

			foreach ( self::addLineNumbers( self::splitNewLines( $this->dumpFunctionCall( $c ) ) ) as $line )
				$lines[] = "    $line";

			$lines[] = '';
		}

		$lines[] = "- {main}";

		return $lines;
	}

	private function dumpVariables( array $vars = null )
	{
		if ( $vars === null )
			return array( 'unavailable' );

		if ( empty( $vars ) )
			return array( 'none' );

		$varLines = array();

		foreach ( $vars as $k => &$v )
		{
			$varLines[] = self::concatenate( array(
			                                      $this->dumpVariable( $k ),
			                                      array( ' = ' ),
			                                      $this->dumpRef( $v ),
			                                      array( ';' ),
			                                 ) );
		}

		return self::addLineNumbers( self::splitNewLines( self::groupLines( $varLines ) ) );
	}

	private function dumpFunctionCall( array $call )
	{
		if ( isset( $call['object'] ) )
			$object = $this->dumpValue( $call['object'] );
		else if ( isset( $call['class'] ) )
			$object = array( @$call['class'] );
		else
			$object = array();

		if ( isset( $call['args'] ) )
			$args = $this->dumpFunctionArgs( $call['args'] );
		else
			$args = array( '( ? )' );

		return self::concatenate( array(
		                               $object,
		                               array( @$call['type'] . @$call['function'] ),
		                               $args,
		                               array( ';' ),
		                          ) );
	}

	private function dumpFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return array( '()' );

		foreach ( $args as $k => &$arg )
		{
			$lineGroups[] = array( $k === 0 ? '( ' : ', ' );
			$lineGroups[] = $this->dumpRef( $arg );
		}

		$lineGroups[] = array( ' )' );

		return self::concatenate( $lineGroups );
	}
}
