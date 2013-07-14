<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\Handler;
use PrettyPrinter\Text;

final class Integer extends Handler
{
	function handleValue( &$int )
	{
		return Text::line( "$int" );
	}
}