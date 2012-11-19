#!/usr/bin/env php
<?php

require_once 'include.php';

$e = ErrorHandler::create();
$e->bind();

$v                 = new stdClass;
$v->foo            = array();
$v->foo['recurse'] =& $v->foo;

error_reporting( -1 );

$f = curl_init();

class A
{
	public function __construct()
	{
		for ( $i = 0; $i < 256; $i++ )
			$this->allBytes .= chr( $i );

		$this->recursiveArray['a']    =& $this->recursiveArray;
		$this->recursiveArray['this'] = $this;
		// trigger_error( 'lol' );
		$a  = 'lol lol';
		$$a = 6;
		unset( $a );
		// $b = 6;
		/** @var $c int */
		print $c;
		$a = null;
		$a->lol();
	}

	private $hebrewChars = "־׀׃׆אבגדהוזחטיךכלםמןנסעףפץצקרשתװױײ׳״";
	private $allBytes = "";
	private $multiLineString = "SELECT blarg
FROM foo
JOIN bah
	ON foo.a = bah.b
WHERE foo.id = 4";
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
	protected $foo = array();
	private $recursiveArray = array();
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


