<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\TypeHandler;
use PrettyPrinter\Utils\Text;

final class Null extends TypeHandler
{
	function handleValue( &$null )
	{
		return new Text( 'null' );
	}
}