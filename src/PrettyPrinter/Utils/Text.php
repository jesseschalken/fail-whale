<?php

namespace PrettyPrinter\Utils;

class Text
{
	static function create( $string = "" )
	{
		return new self( $string );
	}

	private $lines, $hasEndingNewLine, $newLineChar;

	function __construct( $text = "", $newLineChar = "\n" )
	{
		$this->newLineChar      = $newLineChar;
		$this->lines            = new FlatArray( explode( $newLineChar, $text ) );
		$last                   = $this->lines->last();
		$this->hasEndingNewLine = $last->get() === "";

		if ( $this->hasEndingNewLine )
			$last->remove();
	}

	function __toString()
	{
		$newLine = $this->newLineChar;
		$lines   = $this->lines;
		$result  = join( $newLine, $lines->toArray() );

		if ( $this->hasEndingNewLine && !$lines->isEmpty() )
			$result .= $newLine;

		return $result;
	}

	function __clone()
	{
		$this->lines = clone $this->lines;
	}

	function addLines( self $add )
	{
		foreach ( $add->lines as $line )
			$this->lines->add( $line );

		return $this;
	}

	function swapLines( self $other )
	{
		$clone       = clone $this;
		$this->lines = clone $other->lines;

		return $clone;
	}

	/**
	 * @param Text $append
	 *
	 * @return self
	 */
	function appendLines( self $append )
	{
		$space = str_repeat( ' ', $this->width() );
		$last  = $this->lines->last();

		foreach ( $append->lines as $k => $line )
			if ( $k === 0 && $last->valid() )
				$last->set( $last->get() . $line );
			else
				$this->lines->add( $space . $line );

		return $this;
	}

	function width()
	{
		return strlen( $this->lines->last()->getDefault( '' ) );
	}

	/**
	 * @param int $times
	 *
	 * @return self
	 */
	function indent( $times = 1 )
	{
		$space = str_repeat( '  ', $times );

		foreach ( $this->lines as $k => $line )
			if ( $line !== '' )
				$this->lines[ $k ] = $space . $line;

		return $this;
	}

	function addLinesBefore( self $addBefore )
	{
		return $this->addLines( $this->swapLines( $addBefore ) );
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
	 * @param string $line
	 *
	 * @return self
	 */
	function addLine( $line = "" )
	{
		return $this->addLines( new self( $line . $this->newLineChar ) );
	}

	function append( $string )
	{
		return $this->appendLines( new self( $string ) );
	}

	/**
	 * @param $string
	 *
	 * @return self
	 */
	function prepend( $string )
	{
		return $this->prependLines( new self( $string ) );
	}

	function prependLine( $line = "" )
	{
		return $this->addLines( $this->swapLines( new self( $line . $this->newLineChar ) ) );
	}

	function prependLines( self $lines )
	{
		return $this->appendLines( $this->swapLines( $lines ) );
	}

	function padWidth( $width )
	{
		return $this->append( str_repeat( ' ', $width - $this->width() ) );
	}

	function setHasEndingNewline( $value )
	{
		$this->hasEndingNewLine = (bool) $value;

		return $this;
	}
}
