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
		// trigger_error( 'lol' );
		$a  = 'lol lol';
		$$a = 6;
		unset( $a );
		// $b = 6;
		print $c;
		$a = null;
		$a->lol();
	}

	// private $b = 7;
	public $c = array(
		array(
			"SELECT blarg",
			"FROM \"foo\"",
			"WHERE foo.blah = 'lol'",
			"  · AND foo.boo < 3",
			"GROUP BY blarg.lol",
		),
		array( 4.0 ),
		array( 4.2 ),
		array( 4 ),
	);
	private $lol = 5;
	private $blarg = array( null );
}

class Blarg
{
	public static function foo()
	{
		new A( array( array( array( array( 3, 6, 2, 4 ) ) ) ) );
	}
}

Blarg::foo();

echo PhpDump::dump( new A ) . "\n";

print $a;


