<?php

namespace PrettyPrinter\TypeHandlers
{
	use PrettyPrinter\TypeHandler;
	use PrettyPrinter\Utils\Text;

	final class Unknown extends TypeHandler
	{
		function handleValue( &$unknown )
		{
			return new Text( 'unknown type' );
		}
	}
}