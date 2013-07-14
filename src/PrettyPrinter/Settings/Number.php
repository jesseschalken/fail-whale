<?php

namespace PrettyPrinter\Settings;

use PrettyPrinter\Setting;

class Number extends Setting
{
	function set( $v )
	{
		return parent::set( is_int( $v ) ? $v : (float) $v );
	}
}