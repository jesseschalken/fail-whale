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
	public abstract function doPrettyPrint( &$value );

	protected final function prettyPrintRefLines( &$value )
	{
		return $this->prettyPrinter->doPrettyPrint( $value );
	}

	protected final function prettyPrintLines( $value )
	{
		return $this->prettyPrinter->doPrettyPrint( $value );
	}

	protected function prettyPrintVariable( $varName )
	{
		return $this->prettyPrinter->prettyPrintVariable( $varName );
	}

	protected final function prettyPrint( $value )
	{
		return join( "\n", $this->prettyPrintLines( $value ) );
	}

	protected function newId()
	{
		return $this->prettyPrinter->newId();
	}

	protected static function concatenateAligned( array $lineGroups )
	{
		return self::concatenate( $lineGroups, true );
	}

	protected static function concatenate( array $lineGroups, $align = false )
	{
		$result      = array();
		$i           = 0;
		$indentLevel = 0;

		foreach ( $lineGroups as $lines )
		{
			foreach ( $lines as $k => $line )
				if ( $k == 0 && $i != 0 )
					$result[$i - 1] .= $line;
				else
					$result[$i++] = str_repeat( ' ', $indentLevel ) . $line;

			if ( $align )
				$indentLevel += self::textWidth( $lines );
		}

		return $result;
	}

	protected static function arrayGet( array $array, $key, $default = null )
	{
		return array_key_exists( $key, $array ) ? $array[$key] : $default;
	}

	protected static function arrayLastKey( array $array )
	{
		assert( !empty( $array ) );
		end( $array );

		return key( $array );
	}

	protected static function arrayLastValue( array $array )
	{
		assert( !empty( $array ) );
		end( $array );

		return current( $array );
	}

	protected static function alignColumns( array $rows )
	{
		$columnWidths = array();

		foreach ( $rows as $row )
			foreach ( $row as $column => $cell )
				$columnWidths[$column] = max( self::arrayGet( $columnWidths, $column, 0 ), self::textWidth( $cell ) );

		if ( !empty( $columnWidths ) )
			$columnWidths[self::arrayLastKey( $columnWidths )] = 0;

		$lines = array();

		foreach ( $rows as $row )
		{
			$lineGroups = array();

			foreach ( $row as $column => $cell )
				$lineGroups[] = self::padTextWidth( $cell, $columnWidths[$column] );

			foreach ( self::concatenateAligned( $lineGroups ) as $line )
				$lines[] = $line;
		}

		return $lines;
	}

	private static function textWidth( array $lines )
	{
		return empty( $lines ) ? 0 : strlen( self::arrayLastValue( $lines ) );
	}

	private static function padTextWidth( array $lines, $width )
	{
		return self::concatenate( array(
		                               $lines,
		                               array( str_repeat( ' ', max( $width - self::textWidth( $lines ), 0 ) ) ),
		                          ) );
	}

	protected static function splitNewLines( array $lines )
	{
		return explode( "\n", join( "\n", $lines ) );
	}

	protected static function addLineNumbers( array $lines )
	{
		foreach ( $lines as $k => &$line )
			$line = str_pad( $k + 1, 5 ) . " $line";

		return $lines;
	}

	protected static function indentLines( array $lines )
	{
		foreach ( $lines as &$line )
			if ( $line !== "" )
				$line = "    $line";

		return $lines;
	}
}

abstract class CachingPrettyPrinter extends PrettyPrinter
{
	private $cache = array();

	public final function doPrettyPrint( &$value )
	{
		$cacheKey = "$value";

		if ( !isset( $this->cache[$cacheKey] ) )
			$this->cache[$cacheKey] = $this->cacheMiss( $value );

		return $this->cache[$cacheKey];
	}

	protected abstract function cacheMiss( $value );
}

final class StringPrettyPrinter extends CachingPrettyPrinter
{
	private $characterEscapeCache = array(
		"\\" => '\\\\',
		"\$" => '\$',
		"\r" => '\r',
		"\v" => '\v',
		"\f" => '\f',
		"\n" => "\\n\" .\n\"",
		"\t" => "\t",
		"\"" => '\"',
	);

	protected function cacheMiss( $string )
	{
		$escaped = '';
		$length  = strlen( $string );

		for ( $i = 0; $i < $length; $i++ )
		{
			$char = $string[$i];

			if ( !isset( $this->characterEscapeCache[$char] ) )
			{
				$ord = ord( $char );

				$this->characterEscapeCache[$char] =
						$ord >= 32 && $ord <= 126 ? $char : '\x' . substr( '00' . dechex( $ord ), -2 );
			}

			$escaped .= $this->characterEscapeCache[$char];
		}

		return self::splitNewLines( array( "\"$escaped\"" ) );
	}
}

final class BooleanPrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$value )
	{
		return array( $value ? 'true' : 'false' );
	}
}

final class IntegerPrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$int )
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
}

final class ResourcePrettyPrinter extends CachingPrettyPrinter
{
	private $resourceIds = array();

	public function cacheMiss( $resource )
	{
		$string = "$resource";

		if ( !isset( $this->resourceIds[$string] ) )
			$this->resourceIds[$string] = $this->newId();

		return array( get_resource_type( $resource ) . ' ' . $this->resourceIds[$string] );
	}
}

final class ArrayPrettyPrinter extends PrettyPrinter
{
	private $arrayStack = array();
	private $arrayIdsReferenced = array();

	public function doPrettyPrint( &$array )
	{
		foreach ( $this->arrayStack as $id => &$c )
		{
			if ( self::refsEqual( $c, $array ) )
			{
				$this->arrayIdsReferenced[$id] = true;

				return array( "array $id(...)" );
			}
		}

		/**
		 * ( $id1 = array( "recurse" => $id1 ) )
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
		if ( PHP_VERSION_ID < 50317 && count( $this->arrayStack ) > 10 )
			return array( '!maximum depth exceeded!' );

		$id = $this->newId();

		$this->arrayStack[$id] =& $array;

		$result = $this->prettyPrintArrayDeep( $array );
		$result = self::concatenateAligned( array(
		                                         array(
			                                         isset( $this->arrayIdsReferenced[$id] ) ? "array $id" : "array"
		                                         ),
		                                         $result,
		                                    ) );

		unset( $this->arrayStack[$id] );
		unset( $this->arrayIdsReferenced[$id] );

		return $result;
	}

	private function prettyPrintArrayDeep( array $array )
	{
		if ( empty( $array ) )
			return array( '()' );

		$entriesLines  = array();
		$isAssociative = self::isArrayAssociative( $array );
		$lastKey       = self::arrayLastKey( $array );

		foreach ( $array as $k => &$v )
			$entriesLines[] = array(
				$isAssociative ? $this->prettyPrintLines( $k ) : array(),
				$isAssociative ? array( ' => ' ) : array(),
				self::concatenate( array( $this->prettyPrintRefLines( $v ), array( $k === $lastKey ? ' )' : ',' ) ) ),
			);

		return self::concatenateAligned( array( array( '( ' ), self::alignColumns( $entriesLines ) ) );
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
		$aOld   = $a;
		$a      = new stdClass;
		$result = $a === $b;
		$a      = $aOld;

		return $result;
	}
}

final class ObjectPrettyPrinter extends PrettyPrinter
{
	private $objectIds = array();

	public function doPrettyPrint( &$object )
	{
		$class = get_class( $object );
		$hash  = spl_object_hash( $object );

		if ( isset( $this->objectIds[$hash] ) )
			return array( "new $class {$this->objectIds[$hash]} {...}" );

		$this->objectIds[$hash] = $this->newId();

		return self::concatenate( array(
		                               array( "new $class {$this->objectIds[$hash]} {", ),
		                               $this->prettyPrintObjectLinesDeep( $object ),
		                               array( '}', )
		                          ) );
	}

	private function prettyPrintObjectLinesDeep( $object )
	{
		$objectProperties = (array) $object;

		if ( empty( $objectProperties ) )
			return array( '' );

		$propertiesLines = array();

		foreach ( $objectProperties as $property => &$value )
		{
			$access = 'public';

			if ( $property[0] === "\x00" )
			{
				$access   = $property[1] === '*' && $property[2] === "\x00" ? 'protected' : 'private';
				$property = substr( $property, strpos( $property, "\x00", 1 ) + 1 );
			}

			$propertiesLines[] = array(
				self::concatenate( array( array( "$access " ), $this->prettyPrintVariable( $property ) ) ),
				array( ' = ' ),
				self::concatenate( array( $this->prettyPrintRefLines( $value ), array( ';' ) ) ),
			);
		}

		return array_merge( array( '' ),
		                    self::indentLines( self::alignColumns( $propertiesLines ) ),
		                    array( '' ) );
	}
}

final class NullPrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$null )
	{
		return array( 'null' );
	}
}

final class UnknownPrettyPrinter extends PrettyPrinter
{
	public function doPrettyPrint( &$unknown )
	{
		return array( 'unknown type' );
	}
}

final class ValuePrettyPrinter extends PrettyPrinter
{
	/** @var PrettyPrinter[] */
	private $prettyPrinters = array();
	private $variablePrettyPrinter;
	private $nextId = 1;

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

	public final function doPrettyPrint( &$value )
	{
		return $this->prettyPrinters[gettype( $value )]->doPrettyPrint( $value );
	}

	public final function prettyPrintVariable( $varName )
	{
		return $this->variablePrettyPrinter->doPrettyPrint( $varName );
	}

	public final function newId()
	{
		return '#' . $this->nextId++;
	}
}

final class VariablePrettyPrinter extends CachingPrettyPrinter
{
	protected function cacheMiss( $varName )
	{
		if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varName ) )
			return array( "\$$varName" );
		else
			return self::concatenate( array(
			                               array( '${' ),
			                               $this->prettyPrintLines( $varName ),
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
	public function doPrettyPrint( &$exception )
	{
		return array_merge( self::concatenate( array(
		                                            array( 'uncaught ' ),
		                                            $this->prettyPrintExceptionBrief( $exception ),
		                                       ) ),
		                    array(
		                         "",
		                         "global variables:",
		                    ),
		                    self::indentLines( $this->prettyPrintVariables( self::globals() ) ) );
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
		$self    = new self( new ValuePrettyPrinter );
		$class   = get_class( $e );
		$code    = $e->getCode();
		$message = $self->prettyPrint( $e->getMessage() );
		$file    = $self->prettyPrint( $e->getFile() );
		$line    = $self->prettyPrint( $e->getLine() );

		return "uncaught $class, code $code, message $message in file $file, line $line";
	}

	public static function prettyPrintException( Exception $e )
	{
		$self = new self( new ValuePrettyPrinter );

		return join( "\n", $self->doPrettyPrint( $e ) ) . "\n";
	}

	private function prettyPrintExceptionBrief( Exception $e )
	{
		return array_merge( array( get_class( $e ) ),
		                    self::indentLines( array(
		                                            "code    " . $e->getCode(),
		                                            "message " . $this->prettyPrint( $e->getMessage() ),
		                                            "file    " . $this->prettyPrint( $e->getFile() ),
		                                            "line    " . $this->prettyPrint( $e->getLine() ),
		                                       ) ),
		                    array(
		                         "",
		                         "local variables:",
		                    ),
		                    self::indentLines(
			                    $e instanceof ExceptionWithLocalVariables && $e->getLocalVariables() !== null ?
					                    $this->prettyPrintVariables( $e->getLocalVariables() ) :
					                    array( "unavailable" ) ),
		                    array(
		                         "",
		                         "stack trace:",
		                    ),
		                    self::indentLines( $this->prettyPrintStackTrace(
			                                       $e instanceof ExceptionWithFullStackTrace ? $e->getFullStackTrace() :
					                                       $e->getTrace() ) ),
		                    array(
		                         "",
		                         "previous exception:",
		                    ),
		                    self::indentLines( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null ?
				                                       $this->prettyPrintExceptionBrief( $e->getPrevious() ) :
				                                       array( 'none' ) ) );
	}

	private function prettyPrintStackTrace( array $stackTrace )
	{
		$stackFrameLines = array();

		foreach ( $stackTrace as $stackFrame )
		{
			$file            = $this->prettyPrint( self::arrayGet( $stackFrame, 'file' ) );
			$line            = $this->prettyPrint( self::arrayGet( $stackFrame, 'line' ) );
			$stackFrameLines = array_merge( $stackFrameLines,
			                                array( "- file $file, line $line" ),
			                                self::indentLines( self::addLineNumbers( $this->prettyPrintFunctionCall( $stackFrame ) ) ),
			                                array( '' ) );
		}

		$stackFrameLines[] = '- {main}';

		return $stackFrameLines;
	}

	private function prettyPrintVariables( array $vars )
	{
		if ( empty( $vars ) )
			return array( 'none' );

		$varLines = array();

		foreach ( $vars as $k => &$v )
			$varLines[] = array(
				$this->prettyPrintVariable( $k ),
				array( ' = ' ),
				self::concatenate( array(
				                        $this->prettyPrintRefLines( $v ),
				                        array( ';' ),
				                   ) ),
			);

		return self::addLineNumbers( self::alignColumns( $varLines ) );
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		if ( isset( $stackFrame['object'] ) )
			$object = $this->prettyPrintLines( $stackFrame['object'] );
		else if ( isset( $stackFrame['class'] ) )
			$object = array( $stackFrame['class'] );
		else
			$object = array();

		if ( isset( $stackFrame['args'] ) )
			$args = $this->prettyPrintFunctionArgs( $stackFrame['args'] );
		else
			$args = array( '( ? )' );

		return self::concatenateAligned( array(
		                                      $object,
		                                      array(
			                                      self::arrayGet( $stackFrame, 'type', '' ) .
			                                      self::arrayGet( $stackFrame, 'function', '' )
		                                      ),
		                                      $args,
		                                      array( ';' ),
		                                 ) );
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return array( '()' );

		$lines   = array();
		$lastKey = self::arrayLastKey( $args );

		foreach ( $args as $k => &$arg )
			$lines = array_merge( $lines,
			                      self::concatenate( array(
			                                              $this->prettyPrintRefLines( $arg ),
			                                              array( $k === $lastKey ? ' )' : ',' ),
			                                         ) ) );

		return self::concatenateAligned( array( array( '( ' ), $lines ) );
	}
}
