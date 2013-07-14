<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\TypeHandler;
use PrettyPrinter\Utils\Text;

final class Integer extends TypeHandler
{
	function handleValue( &$int )
	{
		return Text::line( "$int" );
	}
}