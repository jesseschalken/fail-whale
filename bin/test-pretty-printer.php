#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

class Foo
{
	public $boo = 'boooo';
	protected $b = 'b';
	private $a = 'a';
}

$recursiveArray = array();
$recursiveArray[ 'recurse' ] =& $recursiveArray;

$tests = array(
	array( null, "null" ),
	array( 0, "0" ),
	array( 0.0, "0.0" ),
	array( 1, "1" ),
	array( -1.99, "-1.99" ),
	array( "lol", '"lol"' ),
	array( array(), "array()" ),
	array( array( "foo" ), 'array( "foo" )' ),
	array( array( "foo", "foo" ),
	       <<<'s'
array( "foo",
       "foo" )
s
	),
	array( new Foo,
	       <<<'s'
new Foo #1 {
    public $boo  = "boooo";
    protected $b = "b";
    private $a   = "a";
}
s
	),
	array( &$recursiveArray,
	       <<<'s'
#1 array( "recurse" => #1 array(...) )
s
	)
);

$prettyPrinter = new \PrettyPrinter\PrettyPrinter;

foreach ( $tests as &$test )
{
	list( $input, $expected ) = $test;

	$actual = $prettyPrinter->prettyPrintRef( $test[ 0 ] );

	assert( $actual === $expected );
}