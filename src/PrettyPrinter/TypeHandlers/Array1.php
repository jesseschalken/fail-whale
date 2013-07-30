<?php

namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\Utils\ArrayUtil;
use PrettyPrinter\Utils\Ref;
use PrettyPrinter\Utils\Table;
use PrettyPrinter\Utils\Text;
use PrettyPrinter\TypeHandler;

/**
 * Called "Array1" because "Array" is a reserved word.
 */
final class Array1 extends TypeHandler
{
	private $arrayStack = array(), $arrayIdsReferenced = array();

	function handleValue( &$array )
	{
		foreach ( $this->arrayStack as $id => &$c )
		{
			if ( Ref::equal( $c, $array ) )
			{
				$this->arrayIdsReferenced[ $id ] = true;

				return new Text( "$id array(...)" );
			}
		}

		/**
		 * In PHP 5.2.4, this class was not able to detect the recursion of the
		 * following structure, resulting in a stack overflow.
		 *
		 *   $a         = new stdClass;
		 *   $a->b      = array();
		 *   $a->b['c'] =& $a->b;
		 *
		 * But PHP 5.3.17 was able. The exact reason I am not sure, but I will enforce
		 * a maximum depth limit for PHP versions older than the earliest for which I
		 * know the recursion detection works.
		 */
		if ( PHP_VERSION_ID < 50317 && count( $this->arrayStack ) > 10 )
			return new Text( '!maximum depth exceeded!' );

		$id                      = $this->newId();
		$this->arrayStack[ $id ] =& $array;
		$result                  = $this->prettyPrintArrayDeep( $id, $array );

		unset( $this->arrayStack[ $id ] );
		unset( $this->arrayIdsReferenced[ $id ] );

		return $result;
	}

	private function prettyPrintArrayDeep( $id, array $array )
	{
		if ( empty( $array ) )
			return new Text( 'array()' );

		$maxEntries    = $this->settings()->maxArrayEntries()->get();
		$isAssociative = ArrayUtil::isAssoc( $array );
		$table         = new Table;

		foreach ( $array as $k => &$v )
		{
			if ( $table->count() >= $maxEntries )
				break;

			$value = $this->prettyPrintRef( $v );

			if ( $table->count() != count( $array ) - 1 )
				$value->append( ',' );

			$table->addRow( $isAssociative
					                ? array( $this->prettyPrint( $k ), $value->prepend( ' => ' ) )
					                : array( $value ) );
		}

		$result = $table->render();

		if ( $table->count() != count( $array ) )
			$result->addLine( '...' );

		$result->wrap( 'array( ', ' )' );

		if ( isset( $this->arrayIdsReferenced[ $id ] ) )
			$result->prepend( "$id " );

		return $result;
	}
}

