<?php

namespace PrettyPrinter\Handlers;

use PrettyPrinter\Handler;
use PrettyPrinter\Table;
use PrettyPrinter\Text;

/**
 * Called "Array1" because "Array" is a reserved word.
 */
final class Array1 extends Handler
{
	private $arrayStack = array(), $arrayIdsReferenced = array();

	function handleValue( &$array )
	{
		foreach ( $this->arrayStack as $id => &$c )
		{
			if ( self::refsEqual( $c, $array ) )
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

	private function prettyPrintArrayDeep( $id, array $array )
	{
		if ( empty( $array ) )
			return Text::line( 'array()' );

		$maxEntries      = $this->settings()->maxArrayEntries;
		$renderMultiLine = $this->settings()->renderArraysMultiLine;
		$isAssociative   = self::isArrayAssociative( $array );
		$table           = new Table;

		foreach ( $array as $k => &$v )
		{
			$row = $table->newRow();

			if ( $table->numRows() > $maxEntries )
				break;

			if ( $isAssociative )
				$row->addCell( $this->prettyPrintRef( $k ) )->addTextCell( ' => ' );

			$value = $this->prettyPrintRef( $v );

			if ( $table->numRows() != count( $array ) )
				$value->append( $renderMultiLine ? ',' : ', ' );

			$row->addCell( $value );
		}

		$result    = $renderMultiLine ? $table->render() : $table->renderOneLine();
		$arrayHead = isset( $this->arrayIdsReferenced[ $id ] ) ? "$id array( " : "array( ";
		$arrayTail = $table->numRows() > $maxEntries ? '... )' : ' )';

		return $result->wrapAligned( $arrayHead, $arrayTail );
	}

	private static function isArrayAssociative( array $array )
	{
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	private static function refsEqual( &$a, &$b )
	{
		$aOld   = $a;
		$a      = new \stdClass;
		$result = $a === $b;
		$a      = $aOld;

		return $result;
	}
}

