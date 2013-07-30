<?php

namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\Utils\Table;
use PrettyPrinter\Utils\Text;
use PrettyPrinter\TypeHandler;

final class Object extends TypeHandler
{
	private $objectIds = array();

	function handleValue( &$object )
	{
		$id       =& $this->objectIds[ spl_object_hash( $object ) ];
		$traverse = !isset( $id ) && $this->maxObjectProperties() > 0;

		if ( !isset( $id ) )
			$id = $this->newId();

		return $this->prettyPrintObject( $object, $traverse, $id );
	}

	private function prettyPrintObject( $object, $traverse, $id )
	{
		$class = get_class( $object );

		if ( !$traverse )
			return new Text( "new $class $id {...}" );

		$objectProperties    = $object instanceof \Closure ? array() : (array) $object;
		$maxObjectProperties = $this->maxObjectProperties();
		$table               = new Table;

		foreach ( $objectProperties as $property => &$value )
		{
			$parts    = explode( "\x00", $property );
			$access   = isset( $parts[ 1 ] ) ? ( $parts[ 1 ] === '*' ? 'protected' : 'private' ) : 'public';
			$property = isset( $parts[ 2 ] ) ? $parts[ 2 ] : $parts[ 0 ];

			$table->addRow( array( $this->prettyPrintVariable( $property )->prepend( "$access " ),
			                       $this->prettyPrintRef( $value )->wrap( ' = ', ';' ) ) );

			if ( $table->count() >= $maxObjectProperties )
				break;
		}

		$result = $table->render();

		if ( $table->count() != count( $objectProperties ) )
			$result->addLine( '...' );

		return $result->indent( 2 )->wrapLines( "new $class $id {", "}" );
	}

	private function maxObjectProperties()
	{
		return $this->settings()->maxObjectProperties()->get();
	}
}

