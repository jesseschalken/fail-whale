<?php

namespace PrettyPrinter;

class Table
{
	/** @var TableRow[] */
	private $rows = array();

	function newRow()
	{
		return $this->rows[ ] = new TableRow;
	}

	function render()
	{
		$this->alignColumns();
		$result = new Text;

		foreach ( $this->rows as $row )
			$result->addLines( $row->render() );

		return $result;
	}

	function renderOneLine()
	{
		$result = new Text;

		foreach ( $this->rows as $row )
			$result->appendLinesAligned( $row->render() );

		return $result;
	}

	function numRows()
	{
		return count( $this->rows );
	}

	private function alignColumns()
	{
		$columnWidths = $this->columnWidths();

		foreach ( $this->rows as $row )
			foreach ( $row->cells() as $column => $text )
				if ( $column !== count( $columnWidths ) - 1 )
					$text->padWidth( $columnWidths[ $column ] );

		return $this;
	}

	private function columnWidths()
	{
		$columnWidths = array();

		foreach ( $this->rows as $row )
			foreach ( $row->cells() as $column => $lines )
				$columnWidths[ $column ] = max( ArrayUtil::get( $columnWidths, $column, 0 ), $lines->width() );

		return $columnWidths;
	}
}
