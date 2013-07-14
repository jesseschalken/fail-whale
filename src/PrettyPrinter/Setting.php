<?php

namespace PrettyPrinter;

class Setting
{
	private $ref;

	function __construct( &$ref )
	{
		$this->ref =& $ref;
	}

	function get() { return $this->ref; }

	function set( $v )
	{
		$this->ref = $v;

		return $this;
	}
}