<?php

namespace PrettyPrinter\Settings
{
	use PrettyPrinter\Setting;

	class Number extends Setting
	{
		function set( $v )
		{
			return parent::set( $v === INF || $v === -INF ? $v : (int) $v );
		}
	}
}