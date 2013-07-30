<?php

namespace PrettyPrinter\Utils;

use PrettyPrinter\Utils\ArrayUtil;
use PrettyPrinter\Utils\Text;

class Table implements \Countable
{
	/** @var (Text[])[] */
	private $rows = array();

	function __clone()
	{
		foreach ( $this->rows as &$row )
			foreach ( $row as &$cell )
				$cell = clone $cell;
	}

	function render()
	{
		$columnWidths = array();
		$result       = new Text;

		/** @var $cell Text */
		foreach ( $this->rows as $cells )
		{
			foreach ( $cells as $column => $cell )
			{
				$width =& $columnWidths[ $column ];
				$width = max( (int) $width, $cell->width() );
			}
		}

		foreach ( $this->rows as $cells )
		{
			$row        = new Text;
			$lastColumn = ArrayUtil::lastKey( $cells );

			foreach ( $cells as $column => $cell )
			{
				if ( $column !== $lastColumn )
					$cell->padWidth( $columnWidths[ $column ] );

				$row->appendLines( $cell );
			}

			$result->addLines( $row );
		}

		return $result;
	}

	function count()
	{
		return count( $this->rows );
	}

	/**
	 * @param Text[] $cells
	 *
	 * @return self
	 */
	function addRow( array $cells )
	{
		foreach ( $cells as &$cell )
			$cell = clone $cell;

		$this->rows[ ] = $cells;

		return $this;
	}
}
