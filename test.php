#!/usr/bin/env php
<?php

require_once 'include.php';

$e = new ErrorHandler;
$e->bind();

error_reporting( -1 );

class A
{
	public function __construct()
	{
		print $a;
	}

	public $c = array(
		array(
			"SELECT blarg",
			"FROM foo",
			"WHERE foo.blah = 'lol'",
			"  AND foo.boo < 3",
			"GROUP BY blarg.lol",
		),
		array( 4 ),
		array( 4 ),
		array( 4 ),
	);
}

echo PhpDump::dump( new A ) . "\n";


print $a;


