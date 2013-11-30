<?php

namespace PrettyPrinter
{
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
}

namespace PrettyPrinter\Settings
{
	use PrettyPrinter\Setting;

	class Bool extends Setting
	{
		function set( $v )
		{
			return parent::set( (bool) $v );
		}

		function yes() { return $this->set( true ); }

		function no() { return $this->set( false ); }
	}

	class Number extends Setting
	{
		function set( $v )
		{
			return parent::set( $v === INF || $v === -INF ? $v : (int) $v );
		}
	}

	class String extends Setting
	{
		function set( $v )
		{
			return parent::set( "$v" );
		}
	}
}

