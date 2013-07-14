<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\Handler;
use PrettyPrinter\Text;

final class Boolean extends Handler
{
	function handleValue( &$value )
	{
		return Text::line( $value ? 'true' : 'false' );
	}
}