<?php

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

