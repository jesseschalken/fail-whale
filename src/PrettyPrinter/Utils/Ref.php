<?php

namespace PrettyPrinter\Utils
{
	class Ref
	{
		static function get( &$ref )
		{
			return $ref;
		}

		static function set( &$ref, $value = null )
		{
			$ref = $value;
		}

		static function &create( $value = null )
		{
			return $value;
		}

		static function equal( &$a, &$b )
		{
			$aOld   = $a;
			$a      = new \stdClass;
			$result = $a === $b;
			$a      = $aOld;

			return $result;
		}
	}
}