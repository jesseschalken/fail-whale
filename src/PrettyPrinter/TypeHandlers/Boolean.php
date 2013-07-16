<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\TypeHandler;
use PrettyPrinter\Utils\Text;

final class Boolean extends TypeHandler
{
	function handleValue( &$value )
	{
		return new Text( $value ? 'true' : 'false' );
	}
}