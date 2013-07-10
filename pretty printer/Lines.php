<?php

namespace PrettyPrinter;

class PrettyPrinterLines
{
	/** @var string[] */
	private $lines = array();

	static function split( $string )
	{
		return new self( explode( "\n", $string ) );
	}

	function __construct( array $lines = array() )
	{
		$this->lines = $lines;
	}

	function prependLine( $line )
	{
		array_unshift( $this->lines, $line );

		return $this;
	}

	function prependLines( self $lines )
	{
		return $this->appendLines( $this->swapLines( $lines ) );
	}

	function addLine( $line = '' )
	{
		$this->lines[ ] = $line;

		return $this;
	}

	function addLines( self $lines )
	{
		$this->lines = array_merge( $this->lines, $lines->lines );

		return $this;
	}

	/**
	 * @param string $string
	 *
	 * @return self
	 */
	function prepend( $string )
	{
		if ( empty( $this->lines ) )
			$this->lines[ ] = $string;
		else
			$this->lines[ 0 ] = $string . $this->lines[ 0 ];

		return $this;
	}

	/**
	 * @param string $string
	 *
	 * @return self
	 */
	function prependAligned( $string )
	{
		$space = self::spaces( strlen( $string ) );

		foreach ( $this->lines as $k => &$line )
			$line = ( $k === 0 ? $string : $space ) . $line;

		return $this;
	}

	function prependLinesAligned( self $lines )
	{
		return $this->appendLinesAligned( $this->swapLines( $lines ) );
	}

	/**
	 * @param string $string
	 *
	 * @return self
	 */
	function append( $string )
	{
		if ( empty( $this->lines ) )
			$this->lines[ ] = $string;
		else
			$this->lines[ count( $this->lines ) - 1 ] .= $string;

		return $this;
	}

	function appendLines( self $lines )
	{
		foreach ( $lines->lines as $k => $line )
			if ( $k === 0 )
				$this->append( $line );
			else
				$this->lines[ ] = $line;

		return $this;
	}

	function appendLinesAligned( self $lines )
	{
		$space = self::spaces( $this->width() );

		foreach ( $lines->lines as $k => $line )
			if ( $k === 0 )
				$this->append( $line );
			else
				$this->lines[ ] = $space . $line;

		return $this;
	}

	function wrap( $prepend, $append )
	{
		return $this->prepend( $prepend )->append( $append );
	}

	function wrapAligned( $prepend, $append )
	{
		return $this->prependAligned( $prepend )->append( $append );
	}

	function wrapLines( $prepend, $append )
	{
		return $this->prependLine( $prepend )->addLine( $append );
	}

	/**
	 * @param string $space
	 *
	 * @return self
	 */
	function indent( $space = '  ' )
	{
		foreach ( $this->lines as &$line )
			if ( $line !== '' )
				$line = $space . $line;

		return $this;
	}

	function padWidth( $width )
	{
		return $this->append( self::spaces( $width - $this->width() ) );
	}

	function width()
	{
		return strlen( pp_array_get( $this->lines, count( $this->lines ) - 1, '' ) );
	}

	function join()
	{
		return join( "\n", $this->lines );
	}

	private static function spaces( $num )
	{
		return str_repeat( ' ', max( $num, 0 ) );
	}

	private function swapLines( self $lines )
	{
		$clone       = clone $this;
		$this->lines = $lines->lines;

		return $clone;
	}
}
