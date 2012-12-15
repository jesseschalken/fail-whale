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

	protected static function concatenateAligned( $lineGroups )
	{
		return self::concatenate( $lineGroups, true );
	}

	protected static function concatenate( array $lineGroups, $align = false )
	{
		$result = array();
		$i      = 0;
		$space  = '';

		foreach ( $lineGroups as $lines ) {
			foreach ( $lines as $k => $line )
				if ( $k == 0 && $i != 0 )
					$result[$i - 1] .= $line;
				else
					$result[$i++] = $space . $line;

			if ( $align )
				$space .= self::spaces( self::textWidth( $lines ) );
		}

		return $result;
	}

	protected static function spaces( $num )
	{
		return str_repeat( ' ', $num );
	}

	protected static function prepend( $prepend, array $lines )
	{
		if ( empty( $lines ) )
			return array( $prepend );

		$lines[0] = $prepend . $lines[0];

		return $lines;
	}

	protected static function prependAligned( $prepend, array $lines )
	{
		$space = self::spaces( strlen( $prepend ) );

		foreach ( $lines as $k => &$line )
			$line = ( $k === 0 ? $prepend : $space ) . $line;

		return $lines;
	}

	protected static function append( array $lines, $append )
	{
		if ( empty( $lines ) )
			return array( $append );

		$lines[count( $lines ) - 1] .= $append;

		return $lines;
	}

	protected static function appendLines( array &$lines, array $append )
	{
		foreach ( $append as $line )
			$lines[] = $line;
	}

	protected static function wrap( $prepend, array $lines, $append = '' )
	{
		return self::prepend( $prepend, self::append( $lines, $append ) );
	}

	protected static function arrayGet( array $array, $key, $default = null )
	{
		return array_key_exists( $key, $array ) ? $array[$key] : $default;
	}

	protected static function alignColumns( array $rows )
	{
		$columnWidths = array();

		foreach ( $rows as $row )
			foreach ( $row as $column => $cell )
				$columnWidths[$column] = max( self::arrayGet( $columnWidths, $column, 0 ),
				                              self::textWidth( $cell ) );

		if ( !empty( $columnWidths ) )
			$columnWidths[count( $columnWidths ) - 1] = 0;

		$lines = array();

		foreach ( $rows as $row ) {
			$lineGroups = array();

			foreach ( $row as $column => $cell )
				$lineGroups[] = self::padTextWidth( $cell, $columnWidths[$column] );

			self::appendLines( $lines, self::concatenateAligned( $lineGroups ) );
		}

		return $lines;
	}

	private static function textWidth( array $lines )
	{
		return strlen( self::arrayGet( $lines, count( $lines ) - 1, '' ) );
	}

	private static function padTextWidth( array $lines, $width )
	{
		return self::append( $lines, self::spaces( max( $width - self::textWidth( $lines ), 0 ) ) );
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
		$result =& $this->cache["$value"];

		if ( !isset( $result ) )
			$result = $this->cacheMiss( $value );

		return $result;
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

		for ( $i = 0; $i < $length; $i++ ) {
			$char        = $string[$i];
			$charEscaped =& $this->characterEscapeCache[$char];

			if ( !isset( $charEscaped ) ) {
				$ord         = ord( $char );
				$charEscaped = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr( '00' . dechex( $ord ), -2 );
			}

			$escaped .= $charEscaped;
		}

		return explode( "\n", "\"$escaped\"" );
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
		$id =& $this->resourceIds["$resource"];

		if ( !isset( $id ) )
			$id = $this->newId();

		return array( get_resource_type( $resource ) . " $id" );
	}
}

final class ArrayPrettyPrinter extends PrettyPrinter
{
	private $arrayStack = array();
	private $arrayIdsReferenced = array();

	public function doPrettyPrint( &$array )
	{
		foreach ( $this->arrayStack as $id => &$c ) {
			if ( self::refsEqual( $c, $array ) ) {
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

		$id                    = $this->newId();
		$this->arrayStack[$id] =& $array;
		$result                = $this->prettyPrintArrayDeep( $id, $array );

		unset( $this->arrayStack[$id] );
		unset( $this->arrayIdsReferenced[$id] );

		return $result;
	}

	private function prettyPrintArrayDeep( $id, array $array )
	{
		if ( empty( $array ) )
			return array( 'array()' );

		$entryRows     = array();
		$isAssociative = self::isArrayAssociative( $array );

		end( $array );
		$lastKey = key( $array );

		foreach ( $array as $k => &$v )
			$entryRows[] = array(
				$isAssociative ? $this->prettyPrintLines( $k ) : array(),
				$isAssociative ? array( ' => ' ) : array(),
				self::append( $this->prettyPrintRefLines( $v ), $k === $lastKey ? ' )' : ',' ),
			);

		return self::prependAligned( isset( $this->arrayIdsReferenced[$id] ) ? "array $id ( " : "array( ",
		                             self::alignColumns( $entryRows ) );
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
		$id    =& $this->objectIds[spl_object_hash( $object )];
		$class = get_class( $object );

		if ( isset( $id ) )
			return array( "new $class $id {...}" );

		$id = $this->newId();

		return array_merge( array( "new $class $id {" ),
		                    self::indentLines( $this->prettyPrintObjectLinesDeep( $object ) ),
		                    array( '}' ) );
	}

	private function prettyPrintObjectLinesDeep( $object )
	{
		$objectProperties = (array) $object;
		$propertyRows     = array();

		foreach ( $objectProperties as $property => &$value ) {
			$parts    = explode( "\x00", $property );
			$access   = isset( $parts[1] ) ? ( $parts[1] == '*' ? 'protected' : 'private' ) : 'public';
			$property = isset( $parts[2] ) ? $parts[2] : $parts[0];

			$propertyRows[] = array(
				self::prepend( "$access ", $this->prettyPrintVariable( $property ) ),
				array( ' = ' ),
				self::append( $this->prettyPrintRefLines( $value ), ';' ),
			);
		}

		return self::alignColumns( $propertyRows );
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
			'unknown type' => new UnknownPrettyPrinter( $this )
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
			return self::wrap( '${', $this->prettyPrintLines( $varName ), '}' );
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
		return array_merge( $this->prettyPrintExceptionBrief( $exception ),
		                    array(
		                         '',
		                         'global variables:'
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
		return array_merge( array( 'uncaught ' . get_class( $e ) ),
		                    self::indentLines( $this->dumpExceptionDescription( $e ) ),
		                    array(
		                         "",
		                         "local variables:",
		                    ),
		                    self::indentLines( $this->prettyPrintExceptionVariables( $e ) ),
		                    array(
		                         "",
		                         "stack trace:",
		                    ),
		                    self::indentLines( $this->prettyPrintExceptionStackTrace( $e ) ),
		                    array(
		                         "",
		                         "previous exception:",
		                    ),
		                    self::indentLines( $this->prettyPrintExceptionPreviousException( $e ) ) );
	}

	private function dumpExceptionDescription( Exception $e )
	{
		return self::alignColumns( array(
		                                array( array( "code: " ), array( $e->getCode() ) ),
		                                array( array( "message: " ), $this->prettyPrintLines( $e->getMessage() ) ),
		                                array( array( "file: " ), $this->prettyPrintLines( $e->getFile() ) ),
		                                array( array( "line: " ), $this->prettyPrintLines( $e->getLine() ) ),
		                           ) );
	}

	private function prettyPrintExceptionPreviousException( Exception $e )
	{
		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null )
			return $this->prettyPrintExceptionBrief( $e->getPrevious() );
		else
			return array( 'none' );
	}

	private function prettyPrintExceptionStackTrace( Exception $e )
	{
		if ( $e instanceof ExceptionWithFullStackTrace )
			$trace = $e->getFullStackTrace();
		else
			$trace = $e->getTrace();

		return $this->prettyPrintStackTrace( $trace );
	}

	private function prettyPrintExceptionVariables( Exception $e )
	{
		if ( $e instanceof ExceptionWithLocalVariables && $e->getLocalVariables() !== null )
			return $this->prettyPrintVariables( $e->getLocalVariables() );
		else
			return array( "unavailable" );
	}

	private function prettyPrintStackTrace( array $stackTrace )
	{
		$stackFrameLines = array();

		foreach ( $stackTrace as $stackFrame ) {
			$file              = $this->prettyPrint( self::arrayGet( $stackFrame, 'file' ) );
			$line              = $this->prettyPrint( self::arrayGet( $stackFrame, 'line' ) );
			$stackFrameLines[] = "- file $file, line $line";

			self::appendLines( $stackFrameLines,
			                   self::indentLines( self::addLineNumbers( $this->prettyPrintFunctionCall( $stackFrame ) ) ) );

			$stackFrameLines[] = "";
		}

		$stackFrameLines[] = '- {main}';

		return $stackFrameLines;
	}

	private function prettyPrintVariables( array $variables )
	{
		if ( empty( $variables ) )
			return array( 'none' );

		$variableRows = array();

		foreach ( $variables as $k => &$v )
			$variableRows[] = array(
				$this->prettyPrintVariable( $k ),
				array( ' = ' ),
				self::append( $this->prettyPrintRefLines( $v ), ';' ),
			);

		return self::addLineNumbers( self::alignColumns( $variableRows ) );
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		if ( isset( $stackFrame['object'] ) )
			$object = $this->prettyPrintLines( $stackFrame['object'] );
		else if ( isset( $stackFrame['class'] ) )
			$object = array( $stackFrame['class'] );
		else
			$object = array( '' );

		if ( isset( $stackFrame['args'] ) )
			$args = $this->prettyPrintFunctionArgs( $stackFrame['args'] );
		else
			$args = array( '( ? )' );

		return self::concatenateAligned( array(
		                                      $object,
		                                      array(
			                                      self::arrayGet( $stackFrame, 'type', '' ) .
			                                      self::arrayGet( $stackFrame, 'function', '' ),
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
		$lastKey = count( $args ) - 1;

		foreach ( $args as $k => &$arg )
			self::appendLines( $lines,
			                   self::append( $this->prettyPrintRefLines( $arg ), $k === $lastKey ? ' )' : ',' ) );

		return self::prependAligned( '( ', $lines );
	}
}
