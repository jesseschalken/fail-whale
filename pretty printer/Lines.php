<?php

class PrettyPrinterLines implements ArrayAccess, IteratorAggregate, Countable
{
	public static function create( array $lines = array() )
	{
		return new self( $lines );
	}

	/**
	 * @var string[]
	 */
	private $lines = array();

	private function __construct( array $lines )
	{
		$this->lines = $lines;
	}

	public function getIterator()
	{
		return new ArrayIterator( $this->lines );
	}

	public function offsetExists( $offset )
	{
		return isset( $this->lines[$offset] );
	}

	public function offsetGet( $offset )
	{
		return $this->lines[$offset];
	}

	public function offsetSet( $offset, $value )
	{
		if ( $offset === null )
			$this->lines[] = $value;
		else
			$this->lines[$offset] = $value;
	}

	public function offsetUnset( $offset )
	{
		unset( $this->lines[$offset] );
	}

	public function lines()
	{
		return $this->lines;
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

	public function append( $string )
	{
		if ( empty( $this->lines ) )
			$this->lines[] = $string;
		else
			$this->lines[count( $this->lines ) - 1] .= $string;

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

	public function isEmpty()
	{
		return count( $this ) == 0;
	}

	public function addLine( $line )
	{
		$this->lines[] = $line;

		return $this;
	}

	public function count()
	{
		return count( $this->lines );
	}

	private static function spaces( $num )
	{
		return str_repeat( ' ', max( $num, 0 ) );
	}

	public function addLines( array $lines )
	{
		$this->lines = array_merge( $this->lines, $lines );

		return $this;
	}

	public function appendLines( array $lines )
	{
		foreach ( $lines as $k => $line )
			if ( $k === 0 )
				$this->append( $line );
			else
				$this->lines[] = $line;

		return $this;
	}

	public function appendLinesAligned( array $lines )
	{
		$space = self::spaces( $this->width() );

		foreach ( $lines as $k => $line )
			if ( $k === 0 )
				$this->append( $line );
			else
				$this->lines[] = $space . $line;

		return $this;
	}

	public function indent()
	{
		foreach ( $this->lines as &$line )
			if ( $line !== '' )
				$line = "    $line";

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
}