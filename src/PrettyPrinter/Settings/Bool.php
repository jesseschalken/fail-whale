<?php

namespace PrettyPrinter\Settings;

use PrettyPrinter\Setting;

class Bool extends Setting
{
	function set( $v )
	{
		return parent::set( (bool) $v );
	}
}