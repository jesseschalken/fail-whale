<?php
namespace PrettyPrinter;

use PrettyPrinter\TypeHandler;
use PrettyPrinter\Utils\Text;

abstract class CachingTypeHandler extends TypeHandler
{
	private $cache = array();

	final function handleValue( &$value )
	{
		$result =& $this->cache[ "$value" ];

		if ( $result === null )
			$result = $this->handleCacheMiss( $value );

		return clone $result;
	}

	/**
	 * @param $value
	 *
	 * @return Text
	 */
	protected abstract function handleCacheMiss( $value );
}