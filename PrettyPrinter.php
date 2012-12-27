<?php

abstract class PrettyPrinterSetting
{
	private $ref;

	public function __construct( &$ref )
	{
		$this->ref =& $ref;
	}

	public function set( $value )
	{
		$this->ref = $value;
	}

	public function get()
	{
		return $this->ref;
	}
}

final class PrettyPrinterSettingYesNo extends PrettyPrinterSetting
{
	public function yes()
	{
		$this->set( true );
	}

	public function no()
	{
		$this->set( false );
	}

	public function set( $value )
	{
		parent::set( (bool) $value );
	}

	public function isYes()
	{
		return $this->get() === true;
	}

	public function isNo()
	{
		return $this->get() === false;
	}
}

final class PrettyPrinterSettingInt extends PrettyPrinterSetting
{
	public function set( $value )
	{
		parent::set( (int) $value );
	}

	public function infinity()
	{
		parent::set( PHP_INT_MAX );
	}
}

final class PrettyPrinterSettings
{
	private $escapeTabs = false;
	private $splitMultiLineStrings = true;
	private $multiLineArrays = true;
	private $maxObjectProperties = PHP_INT_MAX;
	private $maxArrayEntries = PHP_INT_MAX;
	private $maxStringLength = PHP_INT_MAX;

	public function escapeTabs()
	{
		return new PrettyPrinterSettingYesNo( $this->escapeTabs );
	}

	public function splitMultiLineStrings()
	{
		return new PrettyPrinterSettingYesNo( $this->splitMultiLineStrings );
	}

	public function multiLineArrays()
	{
		return new PrettyPrinterSettingYesNo( $this->multiLineArrays );
	}

	public function maxStringLength()
	{
		return new PrettyPrinterSettingInt( $this->maxStringLength );
	}

	public function maxArrayEntries()
	{
		return new PrettyPrinterSettingInt( $this->maxArrayEntries );
	}

	public function maxObjectProperties()
	{
		return new PrettyPrinterSettingInt( $this->maxObjectProperties );
	}

	private function valuePrettyPrinter()
	{
		return new ValuePrettyPrinter( $this );
	}

	private static function joinLines( array $lines )
	{
		return join( "\n", $lines );
	}

	public function prettyPrint( $value )
	{
		return $this->prettyPrintRef( $value );
	}

	public function prettyPrintRef( &$ref )
	{
		return self::joinLines( $this->valuePrettyPrinter()->doPrettyPrint( $ref ) );
	}

	public function prettyPrintException( Exception $e )
	{
		return self::joinLines( $this->valuePrettyPrinter()->prettyPrintException( $e ) );
	}

	public function prettyPrintVariables( array $variables )
	{
		return self::joinLines( $this->valuePrettyPrinter()->prettyPrintVariables( $variables ) );
	}
}

abstract class AbstractPrettyPrinter
{
	/**
	 * @var ValuePrettyPrinter
	 */
	private $valuePrettyPrinter;

	public function __construct( ValuePrettyPrinter $prettyPrinter )
	{
		$this->valuePrettyPrinter = $prettyPrinter;
	}

	/**
	 * @param $value
	 *
	 * @return string[]
	 */
	public abstract function doPrettyPrint( &$value );

	protected final function prettyPrintRefLines( &$value )
	{
		return $this->valuePrettyPrinter->doPrettyPrint( $value );
	}

	protected final function prettyPrintLines( $value )
	{
		return $this->valuePrettyPrinter->doPrettyPrint( $value );
	}

	protected function prettyPrintVariable( $varName )
	{
		return $this->valuePrettyPrinter->prettyPrintVariable( $varName );
	}

	protected function settings()
	{
		return $this->valuePrettyPrinter->settings();
	}

	protected function newId()
	{
		return $this->valuePrettyPrinter->newId();
	}

	protected static function concatenateAligned( array $row )
	{
		$result = array();
		$i      = 0;
		$space  = '';

		foreach ( $row as $cell ) {
			foreach ( $cell as $k => $line )
				if ( $k == 0 && $i != 0 )
					$result[$i - 1] .= $line;
				else
					$result[$i++] = $space . $line;

			$space .= self::spaces( self::textWidth( $cell ) );
		}

		return $result;
	}

	private static function spaces( $num )
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

	protected static function wrapAligned( $prepend, array $lines, $append = '' )
	{
		$space = self::spaces( strlen( $prepend ) );

		foreach ( $lines as $k => &$line )
			$line = ( $k === 0 ? $prepend : $space ) . $line;

		return self::append( $lines, $append );
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

	protected static function arrayGetDefault( array $array, $key, $default = null )
	{
		return array_key_exists( $key, $array ) ? $array[$key] : $default;
	}

	protected static function renderRowsAligned( array $rows )
	{
		return self::concatenateRows( self::padColumns( $rows, self::findColumnWidths( $rows ) ) );
	}

	private static function concatenateRows( array $rows )
	{
		$lines = array();

		foreach ( $rows as $row )
			if ( empty( $row ) )
				$lines[] = '';
			else
				self::appendLines( $lines, self::concatenateAligned( $row ) );

		return $lines;
	}

	private static function padColumns( array $rows, array $columnWidths )
	{
		foreach ( $rows as &$row )
			foreach ( $row as $column => &$cell )
				if ( $column !== count( $columnWidths ) - 1 )
					$cell = self::padTextWidth( $cell, $columnWidths[$column] );

		return $rows;
	}

	private static function findColumnWidths( array $rows )
	{
		$columnWidths = array();

		foreach ( $rows as &$row )
			foreach ( $row as $column => &$cell )
				$columnWidths[$column] = max( self::arrayGetDefault( $columnWidths, $column, 0 ),
				                              self::textWidth( $cell ) );

		return $columnWidths;
	}

	private static function textWidth( array $lines )
	{
		return strlen( self::arrayGetDefault( $lines, count( $lines ) - 1, '' ) );
	}

	private static function padTextWidth( array $lines, $width )
	{
		return self::append( $lines, self::spaces( max( $width - self::textWidth( $lines ), 0 ) ) );
	}

	protected static function indentLines( array $lines )
	{
		foreach ( $lines as &$line )
			if ( $line !== "" )
				$line = "    $line";

		return $lines;
	}

	protected function prettyPrintVariables( array $variables )
	{
		if ( empty( $variables ) )
			return array( 'none' );

		$variableRows = array();

		foreach ( $variables as $k => &$v )
			$variableRows[] = array( $this->prettyPrintVariable( $k ),
			                         array( ' = ' ),
			                         self::append( $this->prettyPrintRefLines( $v ), ';' ) );

		return self::renderRowsAligned( $variableRows );
	}
}

abstract class CachingPrettyPrinter extends AbstractPrettyPrinter
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
	private $characterEscapeCache = array();

	public function __construct( ValuePrettyPrinter $valuePrettyPrinter )
	{
		parent::__construct( $valuePrettyPrinter );

		$tab                        = $this->settings()->escapeTabs()->isYes() ? '\t' : "\t";
		$newLine                    = $this->settings()->splitMultiLineStrings()->isYes() ? "\\n\" .\n\"" : '\n';
		$this->characterEscapeCache = array( "\\" => '\\\\',
		                                     "\$" => '\$',
		                                     "\r" => '\r',
		                                     "\v" => '\v',
		                                     "\t" => $tab,
		                                     "\n" => $newLine,
		                                     "\f" => '\f',
		                                     "\"" => '\"' );
	}

	protected function cacheMiss( $string )
	{
		$escaped   = '';
		$length    = strlen( $string );
		$maxLength = $this->settings()->maxStringLength()->get();

		for ( $i = 0; $i < $length && $i < $maxLength; $i++ ) {
			$char        = $string[$i];
			$charEscaped =& $this->characterEscapeCache[$char];

			if ( !isset( $charEscaped ) ) {
				$ord         = ord( $char );
				$charEscaped = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr( '00' . dechex( $ord ), -2 );
			}

			$escaped .= $charEscaped;
		}

		return explode( "\n", "\"$escaped" . ( $i === $length ? "\"" : "..." ) );
	}
}

final class BooleanPrettyPrinter extends AbstractPrettyPrinter
{
	public function doPrettyPrint( &$value )
	{
		return array( $value ? 'true' : 'false' );
	}
}

final class IntegerPrettyPrinter extends AbstractPrettyPrinter
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

final class ArrayPrettyPrinter extends AbstractPrettyPrinter
{
	private $arrayStack = array();
	private $arrayIdsReferenced = array();

	public function doPrettyPrint( &$array )
	{
		foreach ( $this->arrayStack as $id => &$c ) {
			if ( self::refsEqual( $c, $array ) ) {
				$this->arrayIdsReferenced[$id] = true;

				return array( "array $id (...)" );
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

		$maxEntries      = $this->settings()->maxArrayEntries()->get();
		$resultRows      = array();
		$isAssociative   = self::isArrayAssociative( $array );
		$renderMultiLine = $this->settings()->multiLineArrays()->isYes();
		$numEntriesDone  = 0;

		foreach ( $array as $k => &$v ) {
			if ( $renderMultiLine )
				$row =& $resultRows[];
			else
				$row =& $resultRows[0];

			if ( $row === null )
				$row = array();

			if ( $numEntriesDone >= $maxEntries )
				break;

			if ( $isAssociative ) {
				$row[] = $this->prettyPrintLines( $k );
				$row[] = array( ' => ' );
			}

			$numEntriesDone++;

			if ( $numEntriesDone == count( $array ) )
				$row[] = $this->prettyPrintRefLines( $v );
			else
				$row[] = self::append( $this->prettyPrintRefLines( $v ), $renderMultiLine ? ',' : ', ' );
		}

		return self::wrapAligned( isset( $this->arrayIdsReferenced[$id] ) ? "array $id ( " : "array( ",
		                          self::renderRowsAligned( $resultRows ),
		                          count( $array ) != $numEntriesDone ? '... )' : ' )' );
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

final class ObjectPrettyPrinter extends AbstractPrettyPrinter
{
	private $objectIds = array();

	public function doPrettyPrint( &$object )
	{
		$id       =& $this->objectIds[spl_object_hash( $object )];
		$class    = get_class( $object );
		$traverse = !isset( $id ) && $this->settings()->maxObjectProperties()->get() !== 0;

		if ( !isset( $id ) )
			$id = $this->newId();

		if ( !$traverse )
			return array( "new $class $id {...}" );

		return array_merge( array( "new $class $id {" ),
		                    self::indentLines( $this->prettyPrintObjectLinesDeep( $object ) ),
		                    array( '}' ) );
	}

	private function prettyPrintObjectLinesDeep( $object )
	{
		$objectProperties    = (array) $object;
		$propertyRows        = array();
		$maxObjectProperties = $this->settings()->maxObjectProperties()->get();

		foreach ( $objectProperties as $property => &$value ) {
			$parts    = explode( "\x00", $property );
			$access   = isset( $parts[1] ) ? ( $parts[1] == '*' ? 'protected' : 'private' ) : 'public';
			$property = isset( $parts[2] ) ? $parts[2] : $parts[0];

			$propertyRows[] = array( self::prepend( "$access ", $this->prettyPrintVariable( $property ) ),
			                         array( ' = ' ),
			                         self::append( $this->prettyPrintRefLines( $value ), ';' ) );

			if ( count( $propertyRows ) >= $maxObjectProperties )
				break;
		}

		$lines = self::renderRowsAligned( $propertyRows );

		if ( count( $propertyRows ) !== count( $objectProperties ) )
			$lines[] = '...';

		return $lines;
	}
}

final class NullPrettyPrinter extends AbstractPrettyPrinter
{
	public function doPrettyPrint( &$null )
	{
		return array( 'null' );
	}
}

final class UnknownPrettyPrinter extends AbstractPrettyPrinter
{
	public function doPrettyPrint( &$unknown )
	{
		return array( 'unknown type' );
	}
}

final class ValuePrettyPrinter extends AbstractPrettyPrinter
{
	/** @var AbstractPrettyPrinter[] */
	private $prettyPrinters = array();
	private $variablePrettyPrinter;
	private $exceptionPrettyPrinter;
	private $nextId = 1;
	private $settings;

	public function __construct( PrettyPrinterSettings $settings )
	{
		$this->settings               = $settings;
		$this->variablePrettyPrinter  = new VariablePrettyPrinter( $this );
		$this->exceptionPrettyPrinter = new ExceptionPrettyPrinter( $this );
		$this->prettyPrinters         = array( 'boolean'      => new BooleanPrettyPrinter( $this ),
		                                       'integer'      => new IntegerPrettyPrinter( $this ),
		                                       'double'       => new FloatPrettyPrinter( $this ),
		                                       'string'       => new StringPrettyPrinter( $this ),
		                                       'array'        => new ArrayPrettyPrinter( $this ),
		                                       'object'       => new ObjectPrettyPrinter( $this ),
		                                       'resource'     => new ResourcePrettyPrinter( $this ),
		                                       'NULL'         => new NullPrettyPrinter( $this ),
		                                       'unknown type' => new UnknownPrettyPrinter( $this ) );

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

	public final function prettyPrintException( Exception $e )
	{
		return $this->exceptionPrettyPrinter->doPrettyPrint( $e );
	}

	public final function newId()
	{
		return '#' . $this->nextId++;
	}

	public final function settings()
	{
		return $this->settings;
	}

	public function prettyPrintVariables( array $variables )
	{
		return parent::prettyPrintVariables( $variables );
	}
}

final class VariablePrettyPrinter extends CachingPrettyPrinter
{
	protected function cacheMiss( $varName )
	{
		if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varName ) )
			return array( '$' . $varName );
		else
			return self::wrap( '${', $this->prettyPrintLines( $varName ), '}' );
	}
}

final class ExceptionPrettyPrinter extends AbstractPrettyPrinter
{
	/**
	 * @param Exception $exception
	 *
	 * @return string[]
	 */
	public function doPrettyPrint( &$exception )
	{
		$lines = $this->prettyPrintExceptionNoGlobals( $exception );

		$lines[] = '';
		$lines[] = 'global variables:';

		self::appendLines( $lines, self::indentLines( $this->prettyPrintVariables( self::globals() ) ) );

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

	private function prettyPrintExceptionNoGlobals( Exception $e )
	{
		$lines[] = 'uncaught ' . get_class( $e );

		$descriptionRows = array( array( array( "code " ), array( $e->getCode() ) ),
		                          array( array( "message " ), explode( "\n", $e->getMessage() ) ),
		                          array( array( "file " ), $this->prettyPrintLines( $e->getFile() ) ),
		                          array( array( "line " ), $this->prettyPrintLines( $e->getLine() ) ) );

		self::appendLines( $lines, self::indentLines( self::renderRowsAligned( $descriptionRows ) ) );

		if ( $e instanceof ExceptionWithLocalVariables && $e->getLocalVariables() !== null ) {
			$lines[] = "";
			$lines[] = "local variables:";

			self::appendLines( $lines, self::indentLines( $this->prettyPrintVariables( $e->getLocalVariables() ) ) );
		}

		$lines[] = "";
		$lines[] = "stack trace:";

		$stackTrace = $e instanceof ExceptionWithFullStackTrace ? $e->getFullStackTrace() : $e->getTrace();

		self::appendLines( $lines, self::indentLines( $this->prettyPrintStackTrace( $stackTrace ) ) );

		if ( PHP_VERSION_ID > 50300 && $e->getPrevious() !== null ) {
			$lines[] = "";
			$lines[] = "previous exception:";

			self::appendLines( $lines, self::indentLines( $this->prettyPrintExceptionNoGlobals( $e->getPrevious() ) ) );
		}

		return $lines;
	}

	private function prettyPrintStackTrace( array $stackTrace )
	{
		$lines = array();

		foreach ( $stackTrace as $stackFrame ) {
			$fileAndLine = self::concatenateAligned( array( array( '- file ' ),
			                                                $this->prettyPrintRefLines( $stackFrame['file'] ),
			                                                array( ', line ' ),
			                                                $this->prettyPrintRefLines( $stackFrame['line'] ) ) );

			self::appendLines( $lines,
			                   array_merge( $fileAndLine,
			                                self::indentLines( $this->prettyPrintFunctionCall( $stackFrame ) ),
			                                array( '' ) ) );
		}

		$lines[] = '- {main}';

		return $lines;
	}

	private function prettyPrintFunctionCall( array $stackFrame )
	{
		if ( isset( $stackFrame['object'] ) )
			$object = $this->prettyPrintLines( $stackFrame['object'] );
		else
			$object = array( self::arrayGetDefault( $stackFrame, 'class', '' ) );

		if ( isset( $stackFrame['args'] ) )
			$args = $this->prettyPrintFunctionArgs( $stackFrame['args'] );
		else
			$args = array( '( ? )' );

		return self::concatenateAligned( array( $object,
		                                        array( self::arrayGetDefault( $stackFrame, 'type', '' ) .
		                                               self::arrayGetDefault( $stackFrame, 'function', '' ) ),
		                                        $args,
		                                        array( ';' ) ) );
	}

	private function prettyPrintFunctionArgs( array $args )
	{
		if ( empty( $args ) )
			return array( '()' );

		$pieces[] = array( '( ' );

		foreach ( $args as $k => &$arg ) {
			if ( $k !== 0 )
				$pieces[] = array( ', ' );

			$pieces[] = $this->prettyPrintRefLines( $arg );
		}

		$pieces[] = array( ' )' );

		return self::concatenateAligned( $pieces );
	}
}

interface ExceptionWithLocalVariables
{
	/**
	 * @return array
	 */
	public function getLocalVariables();
}

interface ExceptionWithFullStackTrace
{
	/**
	 * @return array
	 */
	public function getFullStackTrace();
}
