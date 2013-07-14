<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\Handler;
use PrettyPrinter\Handlers\Array1;
use PrettyPrinter\Handlers\Boolean;
use PrettyPrinter\Handlers\Exception;
use PrettyPrinter\Handlers\Float;
use PrettyPrinter\Handlers\Integer;
use PrettyPrinter\Handlers\Null;
use PrettyPrinter\Handlers\Object;
use PrettyPrinter\Handlers\Resource;
use PrettyPrinter\Handlers\String;
use PrettyPrinter\Handlers\Unknown;
use PrettyPrinter\Handlers\Variable;
use PrettyPrinter\PrettyPrinter;
use PrettyPrinter\Table;
use PrettyPrinter\Text;

final class Any extends Handler
{
	/** @var Handler[] */
	private $typeHandlers = array();
	private $variableHandler;
	private $nextId = 1;
	private $settings;

	function __construct( PrettyPrinter $settings )
	{
		$this->settings         = $settings;
		$this->variableHandler  = new Variable( $this );
		$this->typeHandlers     = array( 'boolean'      => new Boolean( $this ),
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

	function settings()
	{
		return $this->settings;
	}

	protected function prettyPrintVariables( array $variables )
	{
		if ( empty( $variables ) )
			return Text::line( 'none' );

		$table = new Table;

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