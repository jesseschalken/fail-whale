<?php

namespace PrettyPrinter\Utils;

class Text
{
	static function create( $string = '' )
	{
		return new self( $string );
	}

	private static function spaces( $num )
	{
		return str_repeat( ' ', max( $num, 0 ) );
	}

	/** @var string[] */
	private $lines = array();

	function __construct( $text = '' )
	{
		if ( "$text" !== "" )
			$this->lines = explode( "\n", $text );
	}

	function __toString()
	{
		return join( "\n", $this->lines );
	}

	function prependLine( $line )
	{
		array_unshift( $this->lines, $line );

		return $this;
	}

	function addLine( $line = '' )
	{
		$this->lines[ ] = "$line";

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
				$this->lines[] = $space . $line;

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
	 * @return \PrettyPrinter\Utils\Text
	 */
	function indent()
	{
		foreach ( $this->lines as &$line )
			if ( $line !== '' )
				$line = "  $line";

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
