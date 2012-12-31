<?php

abstract class AbstractPrettyPrinter
{
	/** @var ValuePrettyPrinter */
	private $valuePrettyPrinter;

	public function __construct( ValuePrettyPrinter $prettyPrinter )
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
	public abstract function doPrettyPrint( &$value );

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

		foreach ( $variables as $k => &$v ) {
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

	public final function doPrettyPrint( &$value )
	{
		$result =& $this->cache["$value"];

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
	public function doPrettyPrint( &$value )
	{
		return self::line( $value ? 'true' : 'false' );
	}
}

final class IntegerPrettyPrinter extends AbstractPrettyPrinter
{
	public function doPrettyPrint( &$int )
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

	public function cacheMiss( $resource )
	{
		$id =& $this->resourceIds["$resource"];

		if ( !isset( $id ) )
			$id = $this->newId();

		return self::line( get_resource_type( $resource ) . " $id" );
	}
}

final class NullPrettyPrinter extends AbstractPrettyPrinter
{
	public function doPrettyPrint( &$null )
	{
		return self::line( 'null' );
	}
}

final class UnknownPrettyPrinter extends AbstractPrettyPrinter
{
	public function doPrettyPrint( &$unknown )
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
			return self::line( '$' . $varName );
		else
			return $this->prettyPrint( $varName )->wrap( '${', '}' );
	}
}

