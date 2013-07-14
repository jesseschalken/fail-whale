<?php

namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\ArrayUtil;
use PrettyPrinter\TypeHandler;
use PrettyPrinter\Table;
use PrettyPrinter\Text;
use PrettyPrinter\Ref;

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

				return Text::line( "$id array(...)" );
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
			return Text::line( '!maximum depth exceeded!' );

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
			return Text::line( 'array()' );

		$maxEntries      = $this->settings()->maxArrayEntries;
		$renderMultiLine = $this->settings()->renderArraysMultiLine;
		$isAssociative   = ArrayUtil::isAssoc( $array );
		$table           = new Table;

		foreach ( $array as $k => &$v )
		{
			if ( $table->count() == $maxEntries )
			{
				$table->addRow( array() );
			}
			else
			{
				$value = $this->prettyPrintRef( $v );

				if ( $table->count() != count( $array ) - 1 )
					$value->append( $renderMultiLine ? ',' : ', ' );

				$table->addRow( $isAssociative
						                ? array( $this->prettyPrint( $k ), Text::line( ' => ' ), $value )
						                : array( $value ) );
			}
		}

		$result = $renderMultiLine ? $table->render() : $table->renderOneLine();

		return $result->wrapAligned( isset( $this->arrayIdsReferenced[ $id ] ) ? "$id array( " : "array( ",
		                             $table->count() > $maxEntries ? '... )' : ' )' );
	}
}

