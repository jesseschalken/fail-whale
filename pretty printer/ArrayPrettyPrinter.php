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

				return self::line( "array $id (...)" );
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
			return self::line( '!maximum depth exceeded!' );

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
			return self::line( 'array()' );

		$maxEntries      = $this->settings()->maxArrayEntries()->get();
		$renderMultiLine = $this->settings()->multiLineArrays()->isYes();
		$isAssociative   = self::isArrayAssociative( $array );
		$table           = new PrettyPrinterTable;

		foreach ( $array as $k => &$v ) {
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

		$lines = $renderMultiLine ? $table->render() : $table->renderOneLine();
		$lines->wrapAligned( isset( $this->arrayIdsReferenced[$id] ) ? "array $id ( " : "array( ",
		                     $table->numRows() > $maxEntries ? '... )' : ' )' );

		return $lines;
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
