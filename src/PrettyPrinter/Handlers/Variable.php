<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\CachingHandler;
use PrettyPrinter\Text;

final class Variable extends CachingHandler
{
	protected function handleCacheMiss( $varName )
	{
		if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varName ) )
			return Text::line( '$' . $varName );
		else
			return $this->prettyPrint( $varName )->wrap( '${', '}' );
	}
}