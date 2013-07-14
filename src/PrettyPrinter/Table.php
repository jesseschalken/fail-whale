<?php

namespace PrettyPrinter;

class Table implements \Countable
{
	private static function renderRow( array $row )
	{
		$result = new Text;

		foreach ( $row as $cell )
			$result->appendLinesAligned( $cell );

		return $result;
	}

	/** @var (Text[])[] */
	private $rows = array();

	function render()
	{
		$this->alignColumns();
		$result = new Text;

		foreach ( $this->rows as $row )
			$result->addLines( self::renderRow( $row ) );

		return $result;
	}

	function renderOneLine()
	{
		$result = new Text;

		foreach ( $this->rows as $row )
			$result->appendLinesAligned( self::renderRow( $row ) );

		return $result;
	}

	function count()
	{
		return count( $this->rows );
	}

	/**
	 * @param Text[] $cell
	 *
	 * @return self
	 */
	function addRow( array $cell )
	{
		$this->rows[ ] = $cell;

		return $this;
	}

	function __clone()
	{
		foreach ( $this->rows as &$row )
			foreach ( $row as &$cell )
				$cell = clone $cell;
	}

	private function alignColumns()
	{
		$columnWidths = $this->columnWidths();

		/** @var $cell Text */
		foreach ( $this->rows as $cells )
			$this->alignRowColumns( $cells, $columnWidths );

		return $this;
	}

	private function columnWidths()
	{
		$columnWidths = array();

		/** @var $cell Text */
		foreach ( $this->rows as $cells )
			foreach ( $cells as $column => $cell )
				$columnWidths[ $column ] = max( ArrayUtil::get( $columnWidths, $column, 0 ), $cell->width() );

		return $columnWidths;
	}

	/**
	 * @param Text[] $cells
	 * @param        $columnWidths
	 */
	private function alignRowColumns( array $cells, array $columnWidths )
	{
		$lastColumn = ArrayUtil::lastKey( $cells );

		foreach ( $cells as $column => $cell )
			if ( $column !== $lastColumn )
				$cell->padWidth( $columnWidths[ $column ] );
	}
}
