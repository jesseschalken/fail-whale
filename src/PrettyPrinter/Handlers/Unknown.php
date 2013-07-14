<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\Handler;
use PrettyPrinter\Text;

final class Unknown extends Handler
{
	function handleValue( &$unknown )
	{
		return Text::line( 'unknown type' );
	}
}