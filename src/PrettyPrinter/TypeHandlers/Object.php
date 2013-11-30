<?php

namespace PrettyPrinter\TypeHandlers
{
	use PrettyPrinter\TypeHandler;
	use PrettyPrinter\Utils\Table;
	use PrettyPrinter\Utils\Text;

	final class Object extends TypeHandler
	{
		private $objectIds = array();

		function handleValue( &$object )
		{
			$id       =& $this->objectIds[ spl_object_hash( $object ) ];
			$traverse = !isset( $id ) && $this->maxProperties() > 0;

			if ( !isset( $id ) )
				$id = $this->newId();

			return $this->prettyPrintObject( $object, $traverse, $id );
		}

		private function prettyPrintObject( $object, $traverse, $id )
		{
			$class = get_class( $object );

			if ( !$traverse )
				return new Text( "new $class $id {...}" );

			$maxProperties = $this->maxProperties();
			$numProperties = 0;
			$table         = new Table;

			for ( $reflection = new \ReflectionObject( $object );
			      $reflection !== false;
			      $reflection = $reflection->getParentClass() )
			{
				foreach ( $reflection->getProperties() as $property )
				{
					if ( $property->isStatic() || $property->class !== $reflection->name )
						continue;

					$numProperties++;

					if ( $table->count() >= $maxProperties )
						continue;

					$property->setAccessible( true );

					$access = Exception::propertyOrMethodAccess( $property );

					$table->addRow( array( $this->prettyPrintVariable( $property->name )->prepend( "$access " ),
					                       $this->prettyPrint( $property->getValue( $object ) )->wrap( ' = ', ';' ) ) );
				}
			}

			$result = $table->render();

			if ( $table->count() != $numProperties )
				$result->addLine( '...' );

			return $result->indent( 2 )->wrapLines( "new $class $id {", "}" );
		}

		private function maxProperties()
		{
			return $this->settings()->maxObjectProperties()->get();
		}
	}
}
