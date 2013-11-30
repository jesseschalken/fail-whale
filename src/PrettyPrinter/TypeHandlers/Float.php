<?php

namespace PrettyPrinter\TypeHandlers
{
	use PrettyPrinter\CachingTypeHandler;
	use PrettyPrinter\Utils\Text;

	final class Float extends CachingTypeHandler
	{
		protected function handleCacheMiss( $float )
		{
			$int = (int) $float;

			return new Text( "$int" === "$float" ? "$float.0" : "$float" );
		}
	}
}