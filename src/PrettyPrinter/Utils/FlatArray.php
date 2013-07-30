<?php

namespace PrettyPrinter\Utils;

/**
 * This class represents a non-associative array. That is, the keys are of form 0, 1, 2...
 *
 * Operations performed on it are the same as a normal array, except it is not allowed to become associative.
 */
class FlatArray implements \ArrayAccess, \Countable, \IteratorAggregate
{
	private $values = array();

	function __construct( array $values = array() )
	{
		$this->values = array_values( $values );
	}

	function offsetExists( $offset )
	{
		return $offset >= 0 && $offset < $this->count();
	}

	function offsetGet( $offset )
	{
		if ( $this->offsetExists( $offset ) )
			return $this->values[ $offset ];
		else
			throw new \Exception;
	}

	function offsetSet( $offset, $value )
	{
		if ( $offset >= 0 && $offset <= $this->count() )
			$this->values[ $offset ] = $value;
		else
			throw new \Exception;
	}

	function offsetUnset( $offset )
	{
		if ( !$this->offsetExists( $offset ) || $offset === $this->count() - 1 )
			unset( $this->values[ $offset ] );
		else
			throw new \Exception;
	}

	function count()
	{
		return count( $this->values );
	}

	function toArray()
	{
		return $this->values;
	}

	function getIterator()
	{
		return $this->at( 0 );
	}

	function add( $value )
	{
		$this[ count( $this ) ] = $value;

		return $this;
	}

	function at( $position )
	{
		return new FlatArrayEntry( $this, $position );
	}

	function first()
	{
		return $this->at( 0 );
	}

	function last()
	{
		return $this->at( count( $this ) - 1 );
	}

	function isEmpty()
	{
		return count( $this ) == 0;
	}
}