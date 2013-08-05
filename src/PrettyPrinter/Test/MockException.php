<?php


namespace PrettyPrinter\Test;

use PrettyPrinter\ExceptionInfo;
use PrettyPrinter\TypeHandlers;

class MockException extends ExceptionInfo
{
	function message()
	{
		return <<<'s'
This is a dummy exception message.

lololool
s;
	}

	function code()
	{
		return 'Dummy exception code';
	}

	function file()
	{
		return '/the/path/to/muh/file';
	}

	function line()
	{
		return 9000;
	}

	function previous()
	{
		return null;
	}

	function localVariables()
	{
		return array(
			'lol' => 8,
			'foo' => 'bar',
		);
	}

	function stackTrace()
	{
		return array(
			array(
				'object'   => new DummyClass1,
				'class'    => 'AClass',
				'args'     => array( new DummyClass2 ),
				'type'     => '->',
				'function' => 'aFunction',
				'file'     => '/path/to/muh/file',
				'line'     => 1928,
			),
		);
	}

	function globalVariables()
	{
		return array();
	}
}