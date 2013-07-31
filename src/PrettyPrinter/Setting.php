<?php

namespace PrettyPrinter;

class Setting
{
	private $ref, $pp;

	function __construct( PrettyPrinter $pp, &$ref )
	{
		$this->pp  = $pp;
		$this->ref =& $ref;
	}

	function get() { return $this->ref; }

	function set( $v )
	{
		$this->ref = $v;

		return $this->pp;
	}
}