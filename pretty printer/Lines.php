<?php

class PrettyPrinterLines
{
	/** @var string[] */
	private $lines = array();

	public static function split( $string )
	{
		return new self( explode( "\n", $string ) );
	}

	public function __construct( array $lines = array() )
	{
		$this->lines = $lines;
	}

	public function prependLine( $line )
	{
		array_unshift( $this->lines, $line );

		return $this;
	}

	public function prependLines( self $lines )
	{
		return $this->appendLines( $this->swapLines( $lines ) );
	}

	public function addLine( $line = '' )
	{
		$this->lines[] = $line;

		return $this;
	}

	public function addLines( self $lines )
	{
		$this->lines = array_merge( $this->lines, $lines->lines );

		return $this;
	}

	public function prepend( $string )
	{
		if ( empty( $this->lines ) )
			$this->lines[] = $string;
		else
			$this->lines[0] = $string . $this->lines[0];

		return $this;
	}

	public function prependAligned( $string )
	{
		$space = self::spaces( strlen( $string ) );

		foreach ( $this->lines as $k => &$line )
			$line = ( $k === 0 ? $string : $space ) . $line;

		return $this;
	}

	public function prependLinesAligned( self $lines )
	{
		return $this->appendLinesAligned( $this->swapLines( $lines ) );
	}

	public function append( $string )
	{
		if ( empty( $this->lines ) )
			$this->lines[] = $string;
		else
			$this->lines[count( $this->lines ) - 1] .= $string;

		return $this;
	}

	public function appendLines( self $lines )
	{
		foreach ( $lines->lines as $k => $line )
			if ( $k === 0 )
				$this->append( $line );
			else
				$this->lines[] = $line;

		return $this;
	}

	public function appendLinesAligned( self $lines )
	{
		$space = self::spaces( $this->width() );

		foreach ( $lines->lines as $k => $line )
			if ( $k === 0 )
				$this->append( $line );
			else
				$this->lines[] = $space . $line;

		return $this;
	}

	public function wrap( $prepend, $append )
	{
		return $this->prepend( $prepend )->append( $append );
	}

	public function wrapAligned( $prepend, $append )
	{
		return $this->prependAligned( $prepend )->append( $append );
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

	public function indent( $space = '  ' )
	{
		foreach ( $this->lines as &$line )
			if ( $line !== '' )
				$line = $space . $line;

		return $this;
	}

	public function padWidth( $width )
	{
		return $this->append( self::spaces( $width - $this->width() ) );
	}

	public function width()
	{
		return empty( $this->lines ) ? 0 : strlen( $this->lines[count( $this->lines ) - 1] );
	}

	public function join()
	{
		return join( "\n", $this->lines );
	}
}
