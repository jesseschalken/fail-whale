<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\TypeHandler;
use PrettyPrinter\Utils\Text;

final class Boolean extends TypeHandler
{
	function handleValue( &$value )
	{
		return Text::line( $value ? 'true' : 'false' );
	}
}