<?php

namespace PrettyPrinter\Utils;

class Text
{
	static function create( $string = "" )
	{
		return new self( $string );
	}

	private static function spaces( $num )
	{
		return str_repeat( ' ', max( $num, 0 ) );
	}

	private $lines = array(), $hasEndingNewLine = false;

	function __construct( $text = "" )
	{
		$this->lines = explode( "\n", $text );

		if ( $this->hasEndingNewLine = $this->lines[ count( $this->lines ) -1 ] === "" )
			array_pop( $this->lines );
	}

	function __toString()
	{
		$joined = join( "\n", $this->lines );

		return $this->hasEndingNewLine && !empty( $this->lines ) ? "$joined\n" : "$joined";
	}

	function setHasEndingNewLine( $value )
	{
		$this->hasEndingNewLine = (bool) $value;

		return $this;
	}

	function prependLine( $line = "" )
	{
		return $this->addLinesBefore( new self( "$line\n" ) );
	}

	function addLine( $line = "" )
	{
		return $this->addLines( new self( "$line\n" ) );
	}

	function addLines( self $add )
	{
		$this->lines = array_merge( $this->lines, $add->lines );

		return $this;
	}

	function addLinesBefore( self $addBefore )
	{
		$this->lines = array_merge( $addBefore->lines, $this->lines );

		return $this;
	}

	/**
	 * @param string $string
	 *
	 * @return \PrettyPrinter\Utils\Text
	 */
	function prepend( $string )
	{
		$space = self::spaces( strlen( $string ) );

		foreach ( $this->lines as $k => &$line )
			$line = ( $k === 0 ? $string : $space ) . $line;

		return $this;
	}

	function prependLines( self $lines )
	{
		return $this->appendLines( $this->swapLines( $lines ) );
	}

	/**
	 * @param string $string
	 *
	 * @return \PrettyPrinter\Utils\Text
	 */
	function append( $string )
	{
		if ( empty( $this->lines ) )
			$this->lines[ ] = $string;
		else
			$this->lines[ count( $this->lines ) - 1 ] .= "$string";

		return $this;
	}

	function appendLines( self $lines )
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

	function wrapLines( $prepend, $append )
	{
		return $this->prependLine( $prepend )->addLine( $append );
	}

	/**
	 * @param int $times
	 *
	 * @return self
	 */
	function indent( $times = 1 )
	{
		$space = self::spaces( $times * 2 );

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
		$lines = $this->lines;

		return empty( $lines ) ? 0 : strlen( $lines[ count( $lines ) - 1 ] );
	}

	private function swapLines( self $lines )
	{
		$clone       = clone $this;
		$this->lines = $lines->lines;

		return $clone;
	}
}
