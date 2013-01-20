<?php

class PrettyPrinterTable
{
	/** @var PrettyPrinterTableRow[] */
	private $rows = array();

	function newRow()
	{
		return $this->rows[ ] = new PrettyPrinterTableRow;
	}

	function render()
	{
		$this->alignColumns();
		$lines = new PrettyPrinterLines;

		foreach ( $this->rows as $row )
			$lines->addLines( $row->render() );

		return $lines;
	}

	function renderOneLine()
	{
		$lines = new PrettyPrinterLines;

		foreach ( $this->rows as $row )
			$lines->appendLinesAligned( $row->render() );

		return $lines;
	}

	function numRows()
	{
		return count( $this->rows );
	}

	private function alignColumns()
	{
		$columnWidths = $this->columnWidths();

		foreach ( $this->rows as $row )
			foreach ( $row->cells() as $column => $lines )
				if ( $column !== count( $columnWidths ) - 1 )
					$lines->padWidth( $columnWidths[ $column ] );

		return $this;
	}

	private function columnWidths()
	{
		$columnWidths = array();

		foreach ( $this->rows as $row )
			foreach ( $row->cells() as $column => $lines )
				$columnWidths[ $column ] = max( pp_array_get( $columnWidths, $column, 0 ), $lines->width() );

		return $columnWidths;
	}
}

class PrettyPrinterTableRow
{
	/** @var PrettyPrinterLines[] */
	private $cells = array();

	function cells()
	{
		return $this->cells;
	}

	function addCell( PrettyPrinterLines $lines )
	{
		$this->cells[ ] = $lines;

		return $this;
	}

	function addTextCell( $text )
	{
		return $this->addCell( new PrettyPrinterLines( array( $text ) ) );
	}

	function render()
	{
		$lines = new PrettyPrinterLines( array( '' ) );

		foreach ( $this->cells as $cell )
			$lines->appendLinesAligned( $cell );

		return $lines;
	}
}
