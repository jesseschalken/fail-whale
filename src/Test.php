<?php

namespace ErrorHandler;

class DummyClass1 {
    private static /** @noinspection PhpUnusedPrivateFieldInspection */
        $privateStatic1;
    protected static $protectedStatic1;
    public static $publicStatic1;
    private /** @noinspection PhpUnusedPrivateFieldInspection */
        $private1;
    protected $protected1;
    public $public1;
}

class DummyClass2 extends DummyClass1 {
    private static /** @noinspection PhpUnusedPrivateFieldInspection */
        $privateStatic2;
    protected static $protectedStatic2;
    public static $publicStatic2;
    private /** @noinspection PhpUnusedPrivateFieldInspection */
        $private2;
    protected $protected2;
    public $public2;
}

class PrettyPrinterTest extends \PHPUnit_Framework_TestCase {
    /**
     * @param mixed $value
     * @param string $pretty
     */
    private static function assertPrettyIs($value, $pretty) {
        self::assertEquals($pretty, Value::introspectValue($value)->toString());
    }

    /**
     * @param mixed $ref
     * @param string $pretty
     */
    private static function assertPrettyRefIs(&$ref, $pretty) {
        self::assertEquals($pretty, Value::introspectRef($ref)->toString());
    }

    function testClosure() {
        self::assertPrettyIs(function () {
            }, <<<'s'
new Closure #1 {
}
s
        );
    }

    function testComplexObject() {
        self::markTestIncomplete();

        self::assertPrettyIs(null, <<<'s'
new PrettyPrinter\TypeHandlers\Any #1 {
    private $typeHandlers    = array( "boolean"      => new PrettyPrinter\TypeHandlers\Boolean #3 {
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "integer"      => new PrettyPrinter\TypeHandlers\Integer #4 {
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "double"       => new PrettyPrinter\TypeHandlers\Float #5 {
                                                            private $cache      = array();
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "bytes"       => new PrettyPrinter\TypeHandlers\String #7 {
                                                            private $characterEscapeCache = array( "\\" => "\\\\",
                                                                                                   "\$" => "\\\$",
                                                                                                   "\r" => "\\r",
                                                                                                   "\v" => "\\v",
                                                                                                   "\f" => "\\f",
                                                                                                   "\"" => "\\\"",
                                                                                                   "	"  => "	",
                                                                                                   "\n" .
                                                                                                   ""   => "\\n\" .\n" .
                                                                                                           "\"" );
                                                            private $cache                = array();
                                                            private $anyHandler           = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "array"        => new PrettyPrinter\TypeHandlers\Array1 #10 {
                                                            private $arrayStack         = array();
                                                            private $arrayIdsReferenced = array();
                                                            private $anyHandler         = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "object"       => new PrettyPrinter\TypeHandlers\Object #13 {
                                                            private $objectIds  = array();
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "resource"     => new PrettyPrinter\TypeHandlers\Resource #15 {
                                                            private $resourceIds = array();
                                                            private $cache       = array();
                                                            private $anyHandler  = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "NULL"         => new PrettyPrinter\TypeHandlers\Null #18 {
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        },
                                      "unknown type" => new PrettyPrinter\TypeHandlers\Unknown #19 {
                                                            private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                                                        } );
    private $variableHandler = new PrettyPrinter\TypeHandlers\Variable #20 {
                                   private $cache      = array();
                                   private $anyHandler = new PrettyPrinter\TypeHandlers\Any #1 {...};
                               };
    private $nextId          = 1;
    private $settings        = new PrettyPrinter\PrettyPrinter #22 {
                                   private $escapeTabsInStrings          = new PrettyPrinter\Settings\Bool #23 {
                                                                               private $value = false;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $splitMultiLineStrings        = new PrettyPrinter\Settings\Bool #24 {
                                                                               private $value = true;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $maxObjectProperties          = new PrettyPrinter\Settings\Number #25 {
                                                                               private $value = 9223372036854775807;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $maxArrayEntries              = new PrettyPrinter\Settings\Number #26 {
                                                                               private $value = 9223372036854775807;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $maxStringLength              = new PrettyPrinter\Settings\Number #27 {
                                                                               private $value = 9223372036854775807;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $showExceptionLocalVariables  = new PrettyPrinter\Settings\Bool #28 {
                                                                               private $value = true;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $showExceptionGlobalVariables = new PrettyPrinter\Settings\Bool #29 {
                                                                               private $value = true;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                                   private $showExceptionStackTrace      = new PrettyPrinter\Settings\Bool #30 {
                                                                               private $value = true;
                                                                               private $pp    = new PrettyPrinter\PrettyPrinter #22 {...};
                                                                           };
                               };
    private $anyHandler      = new PrettyPrinter\TypeHandlers\Any #1 {...};
}
s
        );
    }

    function testException() {
        self::assertEquals(Value::mockException()->toString(), <<<'s'
MuhMockException Dummy exception code in /the/path/to/muh/file:9000

    This is a dummy exception message.

    lololool

local variables:
  $lol = 8;
  $foo = "bar";

stack trace:
  #1 /path/to/muh/file:1928
        new PrettyPrinter\Test\DummyClass1 #1 {
            public $public1       = null;
            private $private1     = null;
            protected $protected1 = null;
        }->aFunction( new PrettyPrinter\Test\DummyClass2 #2 {
                          public $public2       = null;
                          private $private2     = null;
                          protected $protected2 = null;
                          public $public1       = null;
                          private $private1     = null;
                          protected $protected1 = null;
                      } );

  #2 {main}

global variables:
  private static BlahClass::$blahProperty                       = null;
  functionName BlahAnotherClass()::static $public                   = null;
  global ${"lol global"}                                        = null;
  functionName BlahYetAnotherClass::blahMethod()::static $lolStatic = null;
  global $blahVariable                                          = null;


s
        );
    }

    function testMaxArrayEntries() {
        self::assertPrettyIs(range(1, 10), <<<'s'
array( 1,
       2,
       3,
       ... )
s
        );
        self::assertPrettyIs(array("blarg" => "foo",
                                   "bar"   => "bar"),
            <<<'s'
array( "blarg" => "foo",
       "bar"   => "bar" )
s
        );
        self::assertPrettyIs(array("blarg"    => "foo",
                                   "bar"      => "bar",
                                   "bawreara" => "wrjenrg",
                                   "awfjnrg"  => "awrrg"),
            <<<'s'
array( "blarg"    => "foo",
       "bar"      => "bar",
       "bawreara" => "wrjenrg",
       ... )
s
        );
    }

    function testMaxObjectProperties() {
        self::assertPrettyIs(new DummyClass2, <<<'s'
new PrettyPrinter\Test\DummyClass2 #1 {
    public $public2       = null;
    private $private2     = null;
    protected $protected2 = null;
    public $public1       = null;
    private $private1     = null;
    ...
}
s
        );
    }

    function testMaxStringLength() {
        self::assertPrettyIs("wafkjawejf bawjehfb awjhefb j,awhebf ", '"wafkjawejf...');
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
" weaf waef 8we 7f8tweyufgij2k3e wef f\n" .
"sdf wf wef\n" .
"    wef\n" .
"\n" .
"\n" .
"\n" .
"\n" .
"b"
s
        );
    }

    function testObjectArrayRecursion() {
        $object      = new \stdClass;
        $array       = array($object);
        $object->foo =& $array;

        self::assertPrettyRefIs($array, <<<'s'
array( new stdClass #2 {
           public $foo = array( new stdClass #2 {...} );
       } )
s
        );
    }

    function testObjectProperties() {
        self::assertPrettyIs(new DummyClass2, <<<'s'
new PrettyPrinter\Test\DummyClass2 #1 {
    public $public2       = null;
    private $private2     = null;
    protected $protected2 = null;
    public $public1       = null;
    private $private1     = null;
    protected $protected1 = null;
}
s
        );
    }

    function testRecursiveArray() {
        $recursiveArray            = array();
        $recursiveArray['recurse'] =& $recursiveArray;

        self::assertPrettyIs(array(&$recursiveArray, $recursiveArray, $recursiveArray),
            <<<'s'
#1 array( "recurse" => #1 array(...) )
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
        self::assertPrettyIs(2.2250738585072e-308, "2.2250738585072e-308");
        self::assertPrettyIs("lol", '"lol"');
        self::assertPrettyIs(array(), "array()");
        self::assertPrettyIs(array("foo"), 'array( "foo" )');
        self::assertPrettyIs(array("foo", "foo"),
            <<<'s'
array( "foo",
       "foo" )
s
        );
    }

    function testStdClass() {
        $object      = new \stdClass;
        $object->foo = 'bar';

        self::assertPrettyIs($object, <<<'s'
new stdClass #1 {
    public $foo = "bar";
}
s
        );
    }
}


