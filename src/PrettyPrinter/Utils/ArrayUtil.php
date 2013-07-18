<?php

namespace PrettyPrinter\Utils;

class ArrayUtil
{
	static function get( $array, $key, $default = null )
	{
		return isset( $array[ $key ] ) ? $array[ $key ] : $default;
	}

	static function isAssoc( array $array )
	{
		$i = 0;

		foreach ( $array as $k => &$v )
			if ( $k !== $i++ )
				return false;

		return true;
	}

	static function lastKey( array $array )
	{
		foreach ( $array as $k => &$v )
			;

		return isset( $k ) ? $k : null;
	}
}

