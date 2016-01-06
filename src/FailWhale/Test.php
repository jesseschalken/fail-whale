<?php

namespace FailWhale\Test;

use FailWhale\ErrorExceptionWithContext;
use FailWhale\IntrospectionSettings;
use FailWhale\PrettyPrinterSettings;
use FailWhale\Value;

class DummyClass1 {
    private static /** @noinspection PhpUnusedPrivateFieldInspection */
                     $privateStatic1;
    protected static $protectedStatic1;
    public static    $publicStatic1;
    private /** @noinspection PhpUnusedPrivateFieldInspection */
                     $private1;
    protected        $protected1;
    public           $public1;
}

class DummyClass2 extends DummyClass1 {
    private static /** @noinspection PhpUnusedPrivateFieldInspection */
                     $privateStatic2;
    protected static $protectedStatic2;
    public static    $publicStatic2;
    private /** @noinspection PhpUnusedPrivateFieldInspection */
                     $private2;
    protected        $protected2;
    public           $public2;
}

class PrettyPrinterTest extends \PHPUnit_Framework_TestCase {
    /**
     * @param mixed $value
     * @param string $pretty
     */
    private static function assertPrettyIs($value, $pretty) {
        $value = Value::introspect($value);
        $value = Value::fromJSON($value->toJSON());
        self::assertEquals($pretty, $value->toString());
    }

    /**
     * @param mixed $ref
     * @param string $pretty
     */
    private static function assertPrettyRefIs(&$ref, $pretty) {
        $value = Value::introspectRef($ref);
        $value = Value::fromJSON($value->toJSON());
        self::assertEquals($pretty, $value->toString());
    }

    function testClosure() {
        self::assertPrettyIs(function () { }, 'new Closure {}');
    }

    function testException() {
        $mock = Value::mockException();
        $mock = Value::fromJSON($mock->toJSON());

        self::assertEquals($mock->toString(), <<<'s'
MuhMockException Dummy exception code "This is a dummy exception message.

lololool"
#1  /path/to/muh/file:9000
        new FailWhale\Test\DummyClass1 {
            private $private1 = null;
            protected $protected1 = null;
            public $public1 = null;
        }->DummyClass1::aFunction( FailWhale\Test\DummyClass1 $arg1 = new FailWhale\Test\DummyClass1 {
            private $private1 = null;
            protected $protected1 = null;
            public $public1 = null;
        }, array &$anArray = new FailWhale\Test\DummyClass2 {
            private $private2 = null;
            protected $protected2 = null;
            public $public2 = null;
            private $private1 = null;
            protected $protected1 = null;
            public $public1 = null;
        }, 3 more... );
        $lol = 8;
        $foo = "bar";
        5 more...
          8999 line
        > 9000 line
          9001 line
#2  /path/to/muh/file:9000
        aFunction();
#3  [internal function]
        AnClass->aFunction( ? );
8 more...
globals:
    private static BlahClass::$blahProperty = null;
    function blahFunction()::static ${"variable name"} = unknown type 'hurr durr';
    function BlahAnotherClass::blahMethod()::static $lolStatic = null;
    $_SESSION = unknown type;
    global $globalVariable = -2734;
    27 more...
s
        );

        $settings                                 = new PrettyPrinterSettings;
        $settings->showExceptionFunctionArguments = false;
        $settings->indentStackTraceFunctions      = false;

        self::assertEquals(Value::mockException(false)->toString($settings), <<<'s'
MuhMockException Dummy exception code "This is a dummy exception message.

lololool"
#1  /path/to/muh/file:9000 new FailWhale\Test\DummyClass1 {
    private $private1 = null;
    protected $protected1 = null;
    public $public1 = null;
}->DummyClass1::aFunction( ... )
        $lol = 8;
        $foo = "bar";
        5 more...
          8999 line
        > 9000 line
          9001 line
#2  /path/to/muh/file:9000 aFunction()
#3  [internal function] AnClass->aFunction( ? )
8 more...
globals:
    not available
s
        );
    }

    function testMaxArrayEntries() {
        $settings                  = new IntrospectionSettings;
        $settings->maxArrayEntries = 3;
        self::assertEquals(
            <<<'s'
array(
    1,
    2,
    3,
    7 more...
)
s
            ,
            Value::introspect(
                range(1, 10),
                $settings
            )->toString()
        );
        self::assertEquals(
            <<<'s'
array(
    "blarg\x00" => "foo",
    "bar" => "bar",
)
s
            ,
            Value::introspect(
                array("blarg\x00" => "foo",
                      "bar"       => "bar"),
                $settings
            )->toString()
        );
        self::assertEquals(
            <<<'s'
array(
    "blarg" => "foo",
    "bar" => "bar",
    "bawreara" => "wrjenrg",
    1 more...
)
s
            ,
            Value::introspect(
                array("blarg"    => "foo",
                      "bar"      => "bar",
                      "bawreara" => "wrjenrg",
                      "awfjnrg"  => "awrrg"),
                $settings
            )->toString()
        );
    }

    function testMaxObjectProperties() {
        $settings                      = new IntrospectionSettings;
        $settings->maxObjectProperties = 5;
        self::assertEquals(
            <<<'s'
new FailWhale\Test\DummyClass2 {
    private $private2 = null;
    protected $protected2 = null;
    public $public2 = null;
    private $private1 = null;
    protected $protected1 = null;
    1 more...
}
s
            ,
            Value::introspect(new DummyClass2, $settings)->toString()
        );
    }

    function testMaxStringLength() {
        $settings                  = new IntrospectionSettings;
        $settings->maxStringLength = 10;
        self::assertEquals(
            "\"wafkjawejf\" 27 more bytes...",
            Value::introspect("wafkjawejf bawjehfb awjhefb j,awhebf ", $settings)->toString()
        );
    }

    function testMultiLineString() {
        self::assertPrettyIs(<<<'s'
 weaf waef 8we 7f8tweyufgij2k3e wef f
sdf wf wef
    wef




b
s
            ,
            <<<'s'
" weaf waef 8we 7f8tweyufgij2k3e wef f
sdf wf wef
    wef




b"
s
        );
    }

    function testObjectArrayRecursion() {
        $object      = new \stdClass;
        $array       = array($object);
        $object->foo =& $array;

        self::assertPrettyRefIs($array, <<<'s'
array(
    &object001 new stdClass {
        public $foo = array(
            *object001 new stdClass,
        );
    },
)
s
        );
    }

    function testObjectProperties() {
        self::assertPrettyIs(new DummyClass2, <<<'s'
new FailWhale\Test\DummyClass2 {
    private $private2 = null;
    protected $protected2 = null;
    public $public2 = null;
    private $private1 = null;
    protected $protected1 = null;
    public $public1 = null;
}
s
        );
    }

    function testRecursiveArray() {
        $recursiveArray            = array();
        $recursiveArray['recurse'] =& $recursiveArray;

        self::assertPrettyIs(array(&$recursiveArray, $recursiveArray, $recursiveArray),
            <<<'s'
array(
    &array002 array(
        "recurse" => *array002,
    ),
    array(
        "recurse" => &array004 array(
            "recurse" => *array004,
        ),
    ),
    array(
        "recurse" => &array006 array(
            "recurse" => *array006,
        ),
    ),
)
s
        );
    }

    function testSimpleValues() {
        self::assertPrettyIs(null, "null");
        self::assertPrettyIs(false, "false");
        self::assertPrettyIs(true, "true");
        self::assertPrettyIs(INF, "INF");
        self::assertPrettyIs(-INF, "-INF");
        self::assertPrettyIs(NAN, "NAN");
        self::assertPrettyIs((float)0, "0.0");
        self::assertPrettyIs(0, "0");
        self::assertPrettyIs(0.0, "0.0");
        self::assertPrettyIs(1, "1");
        self::assertPrettyIs(100.0000, "100.0");
        self::assertPrettyIs(100.00001, "100.00001");
        self::assertPrettyIs(-1.9999, "-1.9999");
        self::assertPrettyIs(PHP_INT_MAX, (string)PHP_INT_MAX);
        self::assertPrettyIs(~PHP_INT_MAX, (string)~PHP_INT_MAX);
        self::assertPrettyIs(0.0745, "0.0745");
        self::assertPrettyIs(0.33333333333333, "0.33333333333333");
        self::assertPrettyIs(2.2250738585072e-308, "2.2250738585072E-308");
        self::assertPrettyIs("lol", '"lol"');
        self::assertPrettyIs(array(), "array()");
        self::assertPrettyIs(array("foo"),
            <<<'s'
array(
    "foo",
)
s
        );
        self::assertPrettyIs(array("foo", "foo"),
            <<<'s'
array(
    "foo",
    "foo",
)
s
        );
    }

    function testStdClass() {
        $object      = new \stdClass;
        $object->foo = 'bar';

        self::assertPrettyIs($object, <<<'s'
new stdClass {
    public $foo = "bar";
}
s
        );
    }

    function testShortArray() {
        $settings                      = new PrettyPrinterSettings;
        $settings->useShortArraySyntax = true;
        self::assertEquals('[]', Value::introspect(array())->toString($settings));
    }

    function testNoShowObjectPoperties() {
        $settings                       = new PrettyPrinterSettings;
        $settings->showObjectProperties = false;
        self::assertEquals('new stdClass', Value::introspect(new \stdClass)->toString($settings));
    }

    function testLongStrings() {
        $settings                      = new PrettyPrinterSettings;
        $settings->longStringThreshold = 10;

        $string = str_repeat('hello', 10);
        $value  = array($string, $string, $string);
        self::assertEquals(<<<'s'
array(
    &string001 "hellohellohellohellohellohellohellohellohellohello",
    *string001,
    *string001,
)
s
            , Value::introspect($value)->toString($settings));
    }

    function testShortString() {
        $settings                  = new PrettyPrinterSettings;
        $settings->maxStringLength = 10;

        $string = str_repeat('hello', 10);
        self::assertEquals(<<<'s'
"hellohello...
s
            , Value::introspect($string)->toString($settings));
    }

    function testResource() {
        self::assertPrettyIs(fopen('php://memory', 'rb'), 'stream');
    }
}

class IntrospectionTest extends \PHPUnit_Framework_TestCase {
    function testException() {
        ini_set('memory_limit', '-1');
        $e = new ErrorExceptionWithContext('message', 0, 100, 'file', 100, new \Exception);
        $e->setCode('an code');
        $e->setContext(array(
            'foo' => 'bar',
        ));
        Value::introspectException($e)->toHTML();
    }
}

