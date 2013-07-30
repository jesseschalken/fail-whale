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
		$class    = get_class( $object );
		$traverse = !isset( $id ) && $this->maxObjectProperties() > 0;

		if ( !isset( $id ) )
			$id = $this->newId();

		if ( !$traverse )
			return new Text( "new $class $id {...}" );

		return $this->prettyPrintObjectLinesDeep( $object )->indent( 2 )->wrapLines( "new $class $id {", "}" );
	}

	private function prettyPrintObjectLinesDeep( $object )
	{
		$objectProperties    = $object instanceof \Closure ? array() : (array) $object;
		$maxObjectProperties = $this->maxObjectProperties();
		$table               = new Table;

		foreach ( $objectProperties as $property => &$value )
		{
			$parts    = explode( "\x00", $property );
			$access   = isset( $parts[ 1 ] ) ? ( $parts[ 1 ] === '*' ? 'protected' : 'private' ) : 'public';
			$property = isset( $parts[ 2 ] ) ? $parts[ 2 ] : $parts[ 0 ];

			$table->addRow( array(
			                     $this->prettyPrintVariable( $property )->prepend( "$access " ),
			                     new Text( ' = ' ),
			                     $this->prettyPrintRef( $value )->append( ';' ),
			                ) );

			if ( $table->count() >= $maxObjectProperties )
				break;
		}

		$result = $table->render();

		if ( $table->count() != count( $objectProperties ) )
			$result->addLine( '...' );

		return $result;
	}

	private function maxObjectProperties()
	{
		return $this->settings()->maxObjectProperties()->get();
	}
}

