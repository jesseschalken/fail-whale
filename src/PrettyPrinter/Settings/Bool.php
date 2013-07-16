<?php

namespace PrettyPrinter\Settings;

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