<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\CachingTypeHandler;
use PrettyPrinter\Utils\Text;

final class Float extends CachingTypeHandler
{
	protected function handleCacheMiss( $float )
	{
		$int = (int) $float;

		return Text::line( "$int" === "$float" ? "$float.0" : "$float" );
	}
}