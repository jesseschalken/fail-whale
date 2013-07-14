<?php

namespace PrettyPrinter\Handlers;

use PrettyPrinter\Handler;
use PrettyPrinter\Table;
use PrettyPrinter\Text;

final class Object extends Handler
{
	private $objectIds = array();

	function handleValue( &$object )
	{
		$id       =& $this->objectIds[ spl_object_hash( $object ) ];
		$class    = get_class( $object );
		$traverse = !isset( $id ) && $this->settings()->maxObjectProperties > 0;

		if ( !isset( $id ) )
			$id = $this->newId();

		if ( !$traverse )
			return Text::line( "new $class $id {...}" );
		else
			return $this->prettyPrintObjectLinesDeep( $object )->indent( '    ' )->wrapLines( "new $class $id {", "}" );
	}

	private function prettyPrintObjectLinesDeep( $object )
	{
		$objectProperties    = (array) $object;
		$maxObjectProperties = $this->settings()->maxObjectProperties;
		$table               = new Table;

		foreach ( $objectProperties as $property => &$value )
		{
			$parts    = explode( "\x00", $property );
			$access   = isset( $parts[ 1 ] ) ? ( $parts[ 1 ] === '*' ? 'protected' : 'private' ) : 'public';
			$property = isset( $parts[ 2 ] ) ? $parts[ 2 ] : $parts[ 0 ];

			$table->addRow( array(
			                     $this->prettyPrintVariable( $property )->prepend( "$access " ),
			                     Text::line( ' = ' ),
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
}

