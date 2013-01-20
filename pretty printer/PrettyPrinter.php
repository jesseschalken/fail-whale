<?php

function pp_array_get( $array, $key, $default = null )
{
	return isset( $array[ $key ] ) ? $array[ $key ] : $default;
}

abstract class AbstractPrettyPrinter
{
	/** @var ValuePrettyPrinter */
	private $valuePrettyPrinter;

	function __construct( ValuePrettyPrinter $prettyPrinter )
	{
		$this->valuePrettyPrinter = $prettyPrinter;
	}

	protected static function line( $string = '' )
	{
		return self::lines( array( $string ) );
	}

	/**
	 * @param $value
	 *
	 * @return PrettyPrinterLines
	 */
	abstract function doPrettyPrint( &$value );

	protected final function prettyPrintRef( &$value )
	{
		return $this->valuePrettyPrinter->doPrettyPrint( $value );
	}

	protected final function prettyPrint( $value )
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

	protected static function lines( array $lines = array() )
	{
		return new PrettyPrinterLines( $lines );
	}

	protected static function table()
	{
		return new PrettyPrinterTable;
	}

	protected function prettyPrintVariables( array $variables )
	{
		if ( empty( $variables ) )
			return self::line( 'none' );

		$table = self::table();

		foreach ( $variables as $k => &$v )
		{
			$row = $table->newRow();
			$row->addCell( $this->prettyPrintVariable( $k ) );
			$row->addTextCell( ' = ' );
			$row->addCell( $this->prettyPrintRef( $v )->append( ';' ) );
		}

		return $table->render();
	}
}

abstract class CachingPrettyPrinter extends AbstractPrettyPrinter
{
	private $cache = array();

	final function doPrettyPrint( &$value )
	{
		$result =& $this->cache[ "$value" ];

		if ( $result === null )
			$result = $this->cacheMiss( $value );

		return clone $result;
	}

	/**
	 * @param $value
	 *
	 * @return PrettyPrinterLines
	 */
	protected abstract function cacheMiss( $value );
}

final class BooleanPrettyPrinter extends AbstractPrettyPrinter
{
	function doPrettyPrint( &$value )
	{
		return self::line( $value ? 'true' : 'false' );
	}
}

final class IntegerPrettyPrinter extends AbstractPrettyPrinter
{
	function doPrettyPrint( &$int )
	{
		return self::line( "$int" );
	}
}

final class FloatPrettyPrinter extends CachingPrettyPrinter
{
	protected function cacheMiss( $float )
	{
		$int = (int) $float;

		return self::line( "$int" === "$float" ? "$float.0" : "$float" );
	}
}

final class ResourcePrettyPrinter extends CachingPrettyPrinter
{
	private $resourceIds = array();

	function cacheMiss( $resource )
	{
		$id =& $this->resourceIds[ "$resource" ];

		if ( !isset( $id ) )
			$id = $this->newId();

		return self::line( get_resource_type( $resource ) . " $id" );
	}
}

final class NullPrettyPrinter extends AbstractPrettyPrinter
{
	function doPrettyPrint( &$null )
	{
		return self::line( 'null' );
	}
}

final class UnknownPrettyPrinter extends AbstractPrettyPrinter
{
	function doPrettyPrint( &$unknown )
	{
		return self::line( 'unknown type' );
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

	function __construct( PrettyPrinterSettings $settings )
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

	function doPrettyPrint( &$value )
	{
		return $this->prettyPrinters[ gettype( $value ) ]->doPrettyPrint( $value );
	}

	function prettyPrintVariable( $varName )
	{
		return $this->variablePrettyPrinter->doPrettyPrint( $varName );
	}

	function prettyPrintException( Exception $e )
	{
		return $this->exceptionPrettyPrinter->doPrettyPrint( $e );
	}

	function newId()
	{
		return '#' . $this->nextId++;
	}

	function settings()
	{
		return $this->settings;
	}

	function prettyPrintVariables( array $variables )
	{
		return parent::prettyPrintVariables( $variables );
	}
}

final class VariablePrettyPrinter extends CachingPrettyPrinter
{
	protected function cacheMiss( $varName )
	{
		if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varName ) )
			return self::line( '$' . $varName );
		else
			return $this->prettyPrint( $varName )->wrap( '${', '}' );
	}
}

