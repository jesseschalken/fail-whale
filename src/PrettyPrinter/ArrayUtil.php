<?php

namespace PrettyPrinter;

class ArrayUtil
{
	static function get( $array, $key, $default = null )
	{
		return isset( $array[ $key ] ) ? $array[ $key ] : $default;
	}

	static function isAssoc( $array )
	{
		$index = 0;

		foreach ( $array as $key => $value )
			if ( $key !== $index++ )
				return true;

		return false;
	}

	static function lastKey( array $array )
	{
		$keys = array_keys( $array );

		assert( !empty( $keys ) );

		return $keys[ count( $keys ) - 1 ];
	}
}

