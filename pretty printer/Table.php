<?php

class PrettyPrinterTable
{
	/**
	 * @var PrettyPrinterTableRow[]
	 */
	private $rows = array();

	public function newRow()
	{
		return $this->rows[] = new PrettyPrinterTableRow;
	}

	public function render()
	{
		$this->alignColumns();
		$lines = new PrettyPrinterLines;

		foreach ( $this->rows as $row )
			$lines->addLines( $row->render() );

		return $lines;
	}

	public function renderOneLine()
	{
		$lines = new PrettyPrinterLines;

		foreach ( $this->rows as $row )
			$lines->appendLinesAligned( $row->render() );

		return $lines;
	}

	private function alignColumns()
	{
		$columnWidths = $this->columnWidths();

		foreach ( $this->rows as $row )
			foreach ( $row->cells() as $column => $lines )
				if ( $column !== count( $columnWidths ) - 1 )
					$lines->padWidth( $columnWidths[$column] );

		return $this;
	}

	private function columnWidths()
	{
		$columnWidths = array();

		foreach ( $this->rows as $row )
			foreach ( $row->cells() as $column => $lines )
				if ( isset( $columnWidths[$column] ) )
					$columnWidths[$column] = max( $columnWidths[$column], $lines->width() );
				else
					$columnWidths[$column] = $lines->width();

		return $columnWidths;
	}

	public function numRows()
	{
		return count( $this->rows );
	}
}

class PrettyPrinterTableRow
{
	/**
	 * @var PrettyPrinterLines[]
	 */
	private $cells = array();

	public function cells()
	{
		return $this->cells;
	}

	public function addCell( PrettyPrinterLines $lines )
	{
		$this->cells[] = $lines;

		return $this;
	}

	public function addTextCell( $text )
	{
		return $this->addCell( new PrettyPrinterLines( array( $text ) ) );
	}

	public function render()
	{
		$lines = new PrettyPrinterLines( array( '' ) );

		foreach ( $this->cells as $cell )
			$lines->appendLinesAligned( $cell );

		return $lines;
	}
}
