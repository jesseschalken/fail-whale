<?php
namespace PrettyPrinter\TypeHandlers;

use PrettyPrinter\TypeHandler;
use PrettyPrinter\Text;

final class Null extends TypeHandler
{
	function handleValue( &$null )
	{
		return Text::line( 'null' );
	}
}