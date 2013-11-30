<?php

namespace PrettyPrinter\Utils
{
	class ArrayUtil
	{
		static function get( $array, $key, $default = null )
		{
			return isset( $array[ $key ] ) ? $array[ $key ] : $default;
		}

		static function isAssoc( array $array )
		{
			$i = 0;

			/** @noinspection PhpUnusedLocalVariableInspection */
			foreach ( $array as $k => &$v )
				if ( $k !== $i++ )
					return true;

			return false;
		}

		static function lastKey( array $array )
		{
			/** @noinspection PhpUnusedLocalVariableInspection */
			foreach ( $array as $k => &$v )
				;

			return isset( $k ) ? $k : null;
		}
	}
}
