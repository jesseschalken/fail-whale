<?php

abstract class PrettyPrinter
{
	/**
	 * @var ValuePrettyPrinter
	 */
	private $prettyPrinter;

	public function __construct( ValuePrettyPrinter $prettyPrinter )
	{
		$this->prettyPrinter = $prettyPrinter;
	}

	/**
	 * @param $value
	 *
	 * @return string[]
	 */
	public abstract function prettyPrint( &$value );

	protected final function prettyPrintRef( &$value )
	{
		return $this->prettyPrinter->prettyPrint( $value );
	}

	protected final function prettyPrintValue( $value )
	{
		return $this->prettyPrinter->prettyPrint( $value );
	}

	protected function prettyPrintVariable( $varName )
	{
		return $this->prettyPrinter->prettyPrintVariable( $varName );
	}

	protected final function prettyPrintOneLine( $value )
	{
		return join( '', $this->prettyPrintValue( $value ) );
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

abstract class CachingPrettyPrinter extends PrettyPrinter
{
	private $cache = array();

	public final function prettyPrint( &$value )
	{
		$serialized = $this->valueToString( $value );

		if ( !isset( $this->cache[$serialized] ) )
			$this->cache[$serialized] = $this->cacheMiss( $value );

		return $this->cache[$serialized];
	}

	protected abstract function valueToString( $value );

	protected abstract function cacheMiss( $value );
}

final class StringPrettyPrinter extends CachingPrettyPrinter
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

final class BooleanPrettyPrinter extends PrettyPrinter
{
	public function prettyPrint( &$value )
	{
		return array( $value ? 'true' : 'false' );
	}
}

final class IntegerPrettyPrinter extends PrettyPrinter
{
	public function prettyPrint( &$int )
	{
		return array( "$int" );
	}
}

final class FloatPrettyPrinter extends CachingPrettyPrinter
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

final class ResourcePrettyPrinter extends PrettyPrinter
{
	public function prettyPrint( &$resource )
	{
		return array( get_resource_type( $resource ) . ' #' . (int) $resource );
	}
}

final class ArrayPrettyPrinter extends PrettyPrinter
{
	private $arrayContext = array();

	public function prettyPrint( &$array )
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

		$result = $this->prettyPrintArrayDeep( $array );

		array_pop( $this->arrayContext );

		return $result;
	}

	private function prettyPrintArrayEntriesLines( array $array )
	{
		$entriesLines = array();

		$isAssociative = self::isArrayAssociative( $array );

		foreach ( $array as $k => &$v )
		{
			$parts = array();

			if ( $isAssociative )
			{
				$parts[] = $this->prettyPrintValue( $k );
				$parts[] = array( " => " );
			}

			$parts[] = $this->prettyPrintRef( $v );

			$entriesLines[] = self::concatenate( $parts );
		}

		return $entriesLines;
	}

	private function prettyPrintArrayDeep( array $array )
	{
		$entriesLines = $this->prettyPrintArrayEntriesLines( $array );

		if ( empty( $entriesLines ) )
			return array( 'array()' );

		$totalSize = 0;

		foreach ( $entriesLines as $entryLines )
			foreach ( $entryLines as $line )
				$totalSize += strlen( $line );

		if ( $totalSize <= 32 )
			return $this->prettyPrintArrayOneLine( $entriesLines );
		else
			return $this->prettyPrintArrayMultiLine( $entriesLines );
	}

	private function prettyPrintArrayOneLine( array $entriesLines )
	{
		$entries = array();

		foreach ( $entriesLines as $entryLines )
			if ( count( $entryLines ) <= 1 )
				$entries[] = join( '', $entryLines );
			else
				return $this->prettyPrintArrayMultiLine( $entriesLines );

		return array( "array( " . join( ', ', $entries ) . " )" );
	}

	private function prettyPrintArrayMultiLine( array $entriesLines )
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

final class ObjectPrettyPrinter extends CachingPrettyPrinter
{
	private $objectContext = array();

	protected function cacheMiss( $object )
	{
		$hash = $this->valueToString( $object );

		if ( isset( $this->objectContext[$hash] ) )
			return array( 'new ' . get_class( $object ) . ' { *recursion* }' );

		$this->objectContext[$hash] = true;

		$result = $this->prettyPrintObjectLinesDeep( $object );

		unset( $this->objectContext[$hash] );

		return $result;
	}

	protected function valueToString( $object )
	{
		return spl_object_hash( $object );
	}

	private function prettyPrintObjectLinesDeep( $object )
	{
		$className = get_class( $object );

		$propertyLines = $this->prettyPrintObjectPropertiesLines( $object );

		if ( empty( $propertyLines ) )
			return array( "new $className {}" );

		$lines[] = "new $className {";

		foreach ( $propertyLines as $line )
			$lines[] = "    $line";

		$lines[] = '}';

		return $lines;
	}

	private function prettyPrintObjectPropertiesLines( $object )
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
			                                             $this->prettyPrintVariable( $propertyName ),
			                                             array( ' = ' ),
			                                             $this->prettyPrintRef( $propertyValue ),
			                                             array( ';' )
			                                        ) );
		}

		return self::groupLines( $propertiesLines );
	}
}

final class NullPrettyPrinter extends PrettyPrinter
{
	public function prettyPrint( &$null )
	{
		return array( 'null' );
	}
}

final class UnknownPrettyPrinter extends PrettyPrinter
{
	public function prettyPrint( &$unknown )
	{
		return array( 'unknown type' );
	}
}

final class ValuePrettyPrinter extends PrettyPrinter
{
	/** @var PrettyPrinter[] */
	private $prettyPrinters = array();
	private $variablePrettyPrinter;

	public function __construct()
	{
		$this->prettyPrinters = array(
			'boolean'      => new BooleanPrettyPrinter( $this ),
			'integer'      => new IntegerPrettyPrinter( $this ),
			'double'       => new FloatPrettyPrinter( $this ),
			'string'       => new StringPrettyPrinter( $this ),
			'array'        => new ArrayPrettyPrinter( $this ),
			'object'       => new ObjectPrettyPrinter( $this ),
			'resource'     => new ResourcePrettyPrinter( $this ),
			'NULL'         => new NullPrettyPrinter( $this ),
			'unknown type' => new UnknownPrettyPrinter( $this ),
		);

		$this->variablePrettyPrinter = new VariablePrettyPrinter( $this );

		parent::__construct( $this );
	}

	public final function prettyPrint( &$value )
	{
		$printer = $this->prettyPrinters[gettype( $value )];

		return $printer->prettyPrint( $value );
	}

	public final function prettyPrintVariable( $varName )
	{
		return $this->variablePrettyPrinter->prettyPrint( $varName );
	}
}

final class VariablePrettyPrinter extends CachingPrettyPrinter
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
			                               $this->prettyPrintValue( $varName ),
			                               array( '}' ),
			                          ) );
	}
}

final class ExceptionPrettyPrinter extends PrettyPrinter
{
	/**
	 * @param Exception $exception
	 *
	 * @return string[]
	 */
	public function prettyPrint( &$exception )
	{
		$lines    = $this->prettyPrintExceptionBrief( $exception );
		$lines[0] = "uncaught {$lines[0]}";
		$lines[]  = "";
		$lines[]  = "global variables:";

		foreach ( $this->prettyPrintVariables( self::globals() ) as $line )
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

	public static function prettyPrintExceptionOneLine( Exception $e )
	{
		$self = new self( new ValuePrettyPrinter );

		$exceptionClass = get_class( $e );
		$code           = (string) $e->getCode();
		$message        = $self->prettyPrintOneLine( $e->getMessage() );
		$file           = $self->prettyPrintOneLine( $e->getFile() );
		$line           = $self->prettyPrintOneLine( $e->getLine() );

		return "uncaught $exceptionClass, code $code, message $message in file $file, line $line";
	}

	public static function prettyPrintException( Exception $e )
	{
		$self   = new self( new ValuePrettyPrinter );
		$result = '';

		foreach ( $self->prettyPrint( $e ) as $line )
			$result .= "$line\n";

		return $result;
	}

	private function prettyPrintExceptionBrief( Exception $e )
	{
		$exceptionClass = get_class( $e );
		$code           = (string) $e->getCode();
		$message        = $this->prettyPrintOneLine( $e->getMessage() );
		$file           = $this->prettyPrintOneLine( $e->getFile() );
		$line           = $this->prettyPrintOneLine( $e->getLine() );

		$lines[] = "$exceptionClass";
		$lines[] = "  code $code";
		$lines[] = "  message $message";
		$lines[] = "  file $file";
		$lines[] = "  line $line";

		if ( $e instanceof ExceptionWithLocalVariables )
		{
			$lines[] = "";
			$lines[] = "local variables:";

			foreach ( $this->prettyPrintVariables( $e->getLocalVariables() ) as $line )
				$lines[] = "  $line";
		}

		$lines[] = "";
		$lines[] = "stack trace:";

		$trace = $e instanceof ExceptionWithFullStackTrace ? $e->getFullStackTrace() : $e->getTrace();

		foreach ( $this->prettyPrintStackTrace( $trace ) as $line )
			$lines[] = "  $line";

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null )
		{
			$lines[] = "";
			$lines[] = "previous exception:";

			foreach ( $this->prettyPrintExceptionBrief( $e->getPrevious() ) as $line )
				$lines[] = "  $line";
		}

		return $lines;
	}

	private function prettyPrintStackTrace( array $trace = null )
	{
		foreach ( $trace as $c )
		{
			$file = $this->prettyPrintOneLine( @$c['file'] );
			$line = $this->prettyPrintOneLine( @$c['line'] );

			$lines[] = "- file $file, line $line";

			foreach ( self::addLineNumbers( self::splitNewLines( $this->prettyPrintFunctionCall( $c ) ) ) as $line )
				$lines[] = "    $line";

			$lines[] = '';
		}

		$lines[] = "- {main}";

		return $lines;
	}

	private function prettyPrintVariables( array $vars = null )
	{
		if ( $vars === null )
			return array( 'unavailable' );

		if ( empty( $vars ) )
			return array( 'none' );

		$varLines = array();

		foreach ( $vars as $k => &$v )
		{
			$varLines[] = self::concatenate( array(
			                                      $this->prettyPrintVariable( $k ),
			                                      array( ' = ' ),
			                                      $this->prettyPrintRef( $v ),
			                                      array( ';' ),
			                                 ) );
		}

		return self::addLineNumbers( self::splitNewLines( self::groupLines( $varLines ) ) );
	}

	private function prettyPrintFunctionCall( array $call )
	{
		if ( isset( $call['object'] ) )
			$object = $this->prettyPrintValue( $call['object'] );
		else if ( isset( $call['class'] ) )
			$object = array( @$call['class'] );
		else
			$object = array();

		if ( isset( $call['args'] ) )
			$args = $this->prettyPrintFunctionArgs( $call['args'] );
		else
			$args = array( '( ? )' );

		return self::concatenate( array(
		                               $object,
		                               array( @$call['type'] . @$call['function'] ),
		                               $args,
		                               array( ';' ),
		                          ) );
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return array( '()' );

		foreach ( $args as $k => &$arg )
		{
			$lineGroups[] = array( $k === 0 ? '( ' : ', ' );
			$lineGroups[] = $this->prettyPrintRef( $arg );
		}

		$lineGroups[] = array( ' )' );

		return self::concatenate( $lineGroups );
	}
}
