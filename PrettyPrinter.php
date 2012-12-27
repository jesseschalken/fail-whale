<?php

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

