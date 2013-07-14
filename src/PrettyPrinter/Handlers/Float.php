<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\CachingHandler;
use PrettyPrinter\Text;

final class Float extends CachingHandler
{
	protected function cacheMiss( $float )
	{
		$int = (int) $float;

		return Text::line( "$int" === "$float" ? "$float.0" : "$float" );
	}
}