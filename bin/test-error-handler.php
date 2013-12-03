#!/usr/bin/env php
<?php

namespace ErrorHandler;

require_once __DIR__ . '/../vendor/autoload.php';

$e = ErrorHandler::create();
$e->bind();

ini_set( 'display_errors', 1 );

$v                   = new \stdClass;
$v->foo              = array();
$v->foo[ 'recurse' ] =& $v->foo;

error_reporting( -1 );

/** @noinspection PhpUnusedLocalVariableInspection */
$f = curl_init();

class A
{
    function __construct()
    {
        $this->blarg( $this );
    }

    private function blarg( /** @noinspection PhpUnusedParameterInspection */
        A $aaaaaa )
    {
        for ( $i = 0; $i < 256; $i++ )
            $this->allBytes .= chr( $i );

        $this->recursiveArray[ 'a' ]    =& $this->recursiveArray;
        $this->recursiveArray[ 'this' ] = $this;
        // trigger_error( 'lol' );
        $a  = 'lol lol';
        $$a = 6;
        unset( $a );
        // $b = 6;
        // $settings = new PrettyPrinterSettings;
        // print $settings->prettyPrintException( new Exception );
        /** @var $c int */
        print $c;
        // $a = null;
        // $a->lol();
    }

    private /** @noinspection PhpUnusedPrivateFieldInspection */
            $hebrewChars = "־׀׃׆אבגדהוזחטיךכלםמןנסעףפץצקרשתװױײ׳״";
    private $allBytes = "";
    private /** @noinspection PhpUnusedPrivateFieldInspection */
            $multiLineString = "SELECT blarg
FROM foo
JOIN bah
	ON foo.a = bah.b
WHERE foo.id = 4";
    // private $b = 7;
    var $c = array( array( "SELECT blarg",
                           "FROM \"foo\"",
                           "WHERE foo.blah = 'lol'",
                           "  · AND foo.boo < 3",
                           "GROUP BY blarg.lol" ),
                    array( 4.0 ),
                    array( 4.2 ),
                    array( 4 ), );
    private /** @noinspection PhpUnusedPrivateFieldInspection */
            $lol = 5;
    private /** @noinspection PhpUnusedPrivateFieldInspection */
            $blarg = array( null );
    protected $foo = array();
    private $recursiveArray = array();
}

class Blarg
{
    static function foo()
    {
        new A( array( array( array( array( 3, 6, 2, 4 ) ) ) ), 'lol' );
    }
}

function lololololl()
{
    Blarg::foo( 34523466, "\n", 423452345 );
}

lololololl();



