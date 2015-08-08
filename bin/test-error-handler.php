<?php

namespace FailWhale;

require_once __DIR__ . '/../vendor/autoload.php';

set_error_and_exception_handler(function (\Exception $e) {
    $i = new IntrospectionSettings;

    $i->fileNamePrefix  = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    $i->namespacePrefix = '\\FailWhale\\';

    $v = Value::introspectException($e, $i);
    if (PHP_SAPI === 'cli')
        print $v->toString();
    else
        print $v->toHTML();
});

ini_set('display_errors', 1);

$v                 = new \stdClass;
$v->foo            = array();
$v->foo['recurse'] =& $v->foo;

error_reporting(-1);

/** @noinspection PhpUnusedLocalVariableInspection */
$f = curl_init();

class A {
    function __construct($blah) {
        $v = array($this);
        $this->blarg($v);
    }

    private function blarg(/** @noinspection PhpUnusedParameterInspection */
        array &$aaaaaa) {
        for ($i = 0; $i < 256; $i++)
            $this->allBytes .= chr($i);

        $this->recursiveArray['a']    =& $this->recursiveArray;
        $this->recursiveArray['this'] = $this;
        // trigger_error( 'lol' );
        $a  = 'lol lol';
        $$a = 6;
        unset($a);
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
    var $c = array(array("SELECT blarg",
                         "FROM \"foo\"",
                         "WHERE fo\no.blah = 'lol'",
                         "  · AND foo.boo < 3",
                         "GROUP BY blarg.lol"),
                   array(4.0),
                   array(4.2),
                   array(4),);
    private /** @noinspection PhpUnusedPrivateFieldInspection */
        $lol = 5;
    private /** @noinspection PhpUnusedPrivateFieldInspection */
        $blarg = array(null);
    protected $foo = array();
    private $recursiveArray = array();
    static $nums;
}

A::$nums = array(NAN, INF, -INF, 0, 0.0, 1 / 3, pi());

class Blarg {
    static function foo() {
        new A(array(array(array(array(3, 6, 2, 4)))), 'lol');
    }
}

class Lol {
}

function lololololl(/** @noinspection PhpUnusedParameterInspection */
    Lol $foo) {
    /** @noinspection PhpMethodParametersCountMismatchInspection */
    Blarg::foo(34523466, "\n", 423452345);
}

/** @noinspection PhpUnusedLocalVariableInspection */
$f = function () {
    lololololl(new Lol);
};

eval('$f();');
