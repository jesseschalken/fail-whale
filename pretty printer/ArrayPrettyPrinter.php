<?php

final class ArrayPrettyPrinter extends AbstractPrettyPrinter
{
	private $arrayStack = array();
	private $arrayIdsReferenced = array();

	public function doPrettyPrint( &$array )
	{
		foreach ( $this->arrayStack as $id => &$c ) {
			if ( self::refsEqual( $c, $array ) ) {
				$this->arrayIdsReferenced[$id] = true;

				return array( "array $id (...)" );
			}
		}

		/**
		 * ( $id1 = array( "recurse" => $id1 ) )
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
			return array( '!maximum depth exceeded!' );

		$id                    = $this->newId();
		$this->arrayStack[$id] =& $array;
		$result                = $this->prettyPrintArrayDeep( $id, $array );

		unset( $this->arrayStack[$id] );
		unset( $this->arrayIdsReferenced[$id] );

		return $result;
	}

	private function prettyPrintArrayDeep( $id, array $array )
	{
		if ( empty( $array ) )
			return array( 'array()' );

		$maxEntries      = $this->settings()->maxArrayEntries()->get();
		$resultRows      = array();
		$isAssociative   = self::isArrayAssociative( $array );
		$renderMultiLine = $this->settings()->multiLineArrays()->isYes();
		$numEntriesDone  = 0;

		foreach ( $array as $k => &$v ) {
			if ( $renderMultiLine )
				$row =& $resultRows[];
			else
				$row =& $resultRows[0];

			if ( $row === null )
				$row = array();

			if ( $numEntriesDone >= $maxEntries )
				break;

			if ( $isAssociative ) {
				$row[] = $this->prettyPrintLines( $k );
				$row[] = array( ' => ' );
			}

			$numEntriesDone++;

			if ( $numEntriesDone == count( $array ) )
				$row[] = $this->prettyPrintRefLines( $v );
			else
				$row[] = self::append( $this->prettyPrintRefLines( $v ), $renderMultiLine ? ',' : ', ' );
		}

		return self::wrapAligned( isset( $this->arrayIdsReferenced[$id] ) ? "array $id ( " : "array( ",
		                          self::renderRowsAligned( $resultRows ),
		                          count( $array ) != $numEntriesDone ? '... )' : ' )' );
	}

	private static function isArrayAssociative( array $array )
	{
		$i = 0;

		foreach ( $array as $k => $v )
			if ( $k !== $i++ )
				return true;

		return false;
	}

	private static function refsEqual( &$a, &$b )
	{
		$aOld   = $a;
		$a      = new stdClass;
		$result = $a === $b;
		$a      = $aOld;

		return $result;
	}
}

