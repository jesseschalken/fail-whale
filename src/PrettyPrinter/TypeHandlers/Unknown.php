<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\TypeHandler;
use PrettyPrinter\Text;

final class Unknown extends TypeHandler
{
	function handleValue( &$unknown )
	{
		return Text::line( 'unknown type' );
	}
}