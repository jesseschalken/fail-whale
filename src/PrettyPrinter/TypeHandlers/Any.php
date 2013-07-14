<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\PrettyPrinter;
use PrettyPrinter\Utils\Text;
use PrettyPrinter\TypeHandler;

final class Any extends TypeHandler
{
	/** @var TypeHandler[] */
	private $typeHandlers = array();
	private $variableHandler, $nextId = 1, $settings;

	function __construct( PrettyPrinter $settings )
	{
		$this->settings        = $settings;
		$this->variableHandler = new Variable( $this );
		$this->typeHandlers    = array( 'boolean'      => new Boolean( $this ),
		                                'integer'      => new Integer( $this ),
		                                'double'       => new Float( $this ),
		                                'string'       => new String( $this ),
		                                'array'        => new Array1( $this ),
		                                'object'       => new Object( $this ),
		                                'resource'     => new Resource( $this ),
		                                'NULL'         => new Null( $this ),
		                                'unknown type' => new Unknown( $this ) );

		parent::__construct( $this );
	}

	function handleValue( &$value )
	{
		return $this->typeHandlers[ gettype( $value ) ]->handleValue( $value );
	}

	function prettyPrintVariable( $varName )
	{
		return $this->variableHandler->handleValue( $varName );
	}

	function newId()
	{
		return '#' . $this->nextId++;
	}

	function settings() { return $this->settings; }
}