<?php

namespace PrettyPrinter\Settings;

use PrettyPrinter\Setting;

class String extends Setting
{
	function set( $v )
	{
		return parent::set( "$v" );
	}
}