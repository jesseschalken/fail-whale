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
			return self::line( "new $class $id {...}" );
		else
			return $this->prettyPrintObjectLinesDeep( $object )->indent( '    ' )->prependLine( "new $class $id {" )
					->addLine( "}" );
	}

	private function prettyPrintObjectLinesDeep( $object )
	{
		$objectProperties    = (array) $object;
		$maxObjectProperties = $this->settings()->maxObjectProperties()->get();
		$table               = new PrettyPrinterTable;

		foreach ( $objectProperties as $property => &$value ) {
			$parts    = explode( "\x00", $property );
			$access   = isset( $parts[1] ) ? ( $parts[1] == '*' ? 'protected' : 'private' ) : 'public';
			$property = isset( $parts[2] ) ? $parts[2] : $parts[0];

			$row = $table->newRow();
			$row->addCell( $this->prettyPrintVariable( $property )->prepend( "$access " ) );
			$row->addTextCell( ' = ' );
			$row->addCell( $this->prettyPrintRef( $value )->append( ';' ) );

			if ( $table->numRows() >= $maxObjectProperties )
				break;
		}

		$lines = $table->render();

		if ( $table->numRows() !== count( $objectProperties ) )
			$lines->addLine( '...' );

		return $lines;
	}
}

