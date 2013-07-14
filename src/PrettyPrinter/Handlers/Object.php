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

	private function prettyPrintObjectLinesDeep( $object )
	{
		$objectProperties    = (array) $object;
		$maxObjectProperties = $this->settings()->maxObjectProperties;
		$table               = new Table;

		foreach ( $objectProperties as $property => &$value )
		{
			$parts    = explode( "\x00", $property );
			$access   = isset( $parts[ 1 ] ) ? ( $parts[ 1 ] == '*' ? 'protected' : 'private' ) : 'public';
			$property = isset( $parts[ 2 ] ) ? $parts[ 2 ] : $parts[ 0 ];

			$row = $table->newRow();
			$row->addCell( $this->prettyPrintVariable( $property )->prepend( "$access " ) );
			$row->addTextCell( ' = ' );
			$row->addCell( $this->prettyPrintRef( $value )->append( ';' ) );

			if ( $table->numRows() >= $maxObjectProperties )
				break;
		}

		$result = $table->render();

		if ( $table->numRows() !== count( $objectProperties ) )
			$result->addLine( '...' );

		return $result;
	}
}

