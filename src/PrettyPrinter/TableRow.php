<?php
namespace PrettyPrinter;

class TableRow
{
	/** @var Text[] */
	private $cells = array();

	function cells()
	{
		return $this->cells;
	}

	function addCell( Text $lines )
	{
		$this->cells[ ] = $lines;

		return $this;
	}

	function addTextCell( $text )
	{
		return $this->addCell( Text::line( $text ) );
	}

	function render()
	{
		$lines = Text::line( '' );

		foreach ( $this->cells as $cell )
			$lines->appendLinesAligned( $cell );

		return $lines;
	}
}