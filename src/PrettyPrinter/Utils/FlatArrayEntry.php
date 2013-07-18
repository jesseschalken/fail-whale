<?php

namespace PrettyPrinter\Utils;

/**
 * This is basically just a pointer into an entry in a FlatArray. It also doubles as a seekable iterator.
 *
 * Note that since this is just a pair of FlatArray and an offset, the entry it points to might not exist.
 */
class FlatArrayEntry implements \SeekableIterator
{
	/** @var FlatArray */
	private $flatArray, $offset;

	function __construct( FlatArray $array, $offset )
	{
		$this->flatArray = $array;
		$this->offset    = $offset;
	}

	function current()
	{
		return $this->flatArray->offsetGet( $this->offset );
	}

	function next()
	{
		$this->offset++;

		return $this;
	}

	function key()
	{
		return $this->offset;
	}

	function valid()
	{
		return $this->flatArray->offsetExists( $this->offset );
	}

	function rewind()
	{
		return $this->seek( 0 );
	}

	function seek( $position )
	{
		$this->offset = $position;

		return $this;
	}

	function remove()
	{
		$this->flatArray->offsetUnset( $this->offset );

		return $this;
	}

	function set( $value )
	{
		$this->flatArray->offsetSet( $this->offset, $value );

		return $this;
	}

	function get()
	{
		return $this->current();
	}

	function getDefault( $default )
	{
		return $this->valid() ? $this->get() : $default;
	}
}