<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\Handler;
use PrettyPrinter\Text;

final class Null extends Handler
{
	function handleValue( &$null )
	{
		return Text::line( 'null' );
	}
}