<?php

namespace PrettyPrinter
{
	use PrettyPrinter\Test\DummyClass2;
	use PrettyPrinter\Test\MockException;

	class PrettyPrinterTest extends \PHPUnit_Framework_TestCase
	{
		private static function pp()
		{
			return new PrettyPrinter;
		}

		function testSimpleValues()
		{
			$pp = self::pp();
			$pp->assertPrettyIs( null, "null" );
			$pp->assertPrettyIs( false, "false" );
			$pp->assertPrettyIs( true, "true" );
			$pp->assertPrettyIs( INF, "INF" );
			$pp->assertPrettyIs( -INF, "-INF" );
			$pp->assertPrettyIs( (float) 0, "0.0" );
			$pp->assertPrettyIs( 0, "0" );
			$pp->assertPrettyIs( 0.0, "0.0" );
			$pp->assertPrettyIs( 1, "1" );
			$pp->assertPrettyIs( -1.99, "-1.99" );
			$pp->assertPrettyIs( "lol", '"lol"' );
			$pp->assertPrettyIs( array(), "array()" );
			$pp->assertPrettyIs( array( "foo" ), 'array( "foo" )' );
			$pp->assertPrettyIs( array( "foo", "foo" ),
				<<<'s'
array( "foo",
       "foo" )
s
			);
		}

		function testRecursiveArray()
		{
			$recursiveArray              = array();
			$recursiveArray[ 'recurse' ] =& $recursiveArray;

			self::pp()->assertPrettyRefIs( $recursiveArray,
				<<<'s'
#1 array( "recurse" => #1 array(...) )
s
			);
		}

		function testMultiLineString()
		{
			self::pp()->assertPrettyIs( <<<'s'
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

		function testComplexObject()
		{
			$this->markTestIncomplete();

			self::pp()->maxArrayEntries()->set( 10 )->assertPrettyIs( new Memory, <<<'s'
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
                                      "string"       => new PrettyPrinter\TypeHandlers\String #7 {
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

		function testClosure()
		{
			self::pp()->assertPrettyIs( function () { }, <<<'s'
new Closure #1 {
}
s
			);
		}

		function testStdClass()
		{
			$object      = new \stdClass;
			$object->foo = 'bar';

			self::pp()->assertPrettyIs( $object, <<<'s'
new stdClass #1 {
    public $foo = "bar";
}
s
			);
		}

		function testObjectArrayRecursion()
		{
			$object      = new \stdClass;
			$array       = array( $object );
			$object->foo =& $array;

			self::pp()->assertPrettyRefIs( $array, <<<'s'
array( new stdClass #2 {
           public $foo = array( new stdClass #2 {...} );
       } )
s
			);
		}

		function testObjectProperties()
		{
			self::pp()->assertPrettyIs( new DummyClass2, <<<'s'
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

		function testMaxObjectProperties()
		{
			self::pp()->maxObjectProperties()->set( 5 )->assertPrettyIs( new DummyClass2, <<<'s'
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

		function testMaxArrayEntries()
		{
			self::pp()->maxArrayEntries()->set( 3 )
			    ->assertPrettyIs( range( 1, 10 ), <<<'s'
array( 1,
       2,
       3,
       ... )
s
					)
			    ->assertPrettyIs( array( "blarg" => "foo",
			                             "bar"   => "bar" ),
				    <<<'s'
array( "blarg" => "foo",
       "bar"   => "bar" )
s
					)
			    ->assertPrettyIs( array( "blarg"    => "foo",
			                             "bar"      => "bar",
			                             "bawreara" => "wrjenrg",
			                             "awfjnrg"  => "awrrg" ),
				    <<<'s'
array( "blarg"    => "foo",
       "bar"      => "bar",
       "bawreara" => "wrjenrg",
       ... )
s
					);
		}

		function testMaxStringLength()
		{
			self::pp()->maxStringLength()->set( 10 )
			    ->assertPrettyIs( "wafkjawejf bawjehfb awjhefb j,awhebf ", '"wafkjawejf...' );
		}

		function testException()
		{
			self::assertEquals( self::pp()->prettyPrintExceptionInfo( new MockException ), <<<'s'
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
  function BlahAnotherClass()::static $public                   = null;
  global ${"lol global"}                                        = null;
  function BlahYetAnotherClass::blahMethod()::static $lolStatic = null;
  $blahVariable                                                 = null;


s
			);
		}
	}
}

namespace PrettyPrinter\Test
{
	use PrettyPrinter\Memory;
	use PrettyPrinter\Types;
	use PrettyPrinter\Types\ReflectedGlobal;
	use PrettyPrinter\Utils\Ref;

	class DummyClass1
	{
		public static $publicStatic1;
		private static /** @noinspection PhpUnusedPrivateFieldInspection */
				$privateStatic1;
		protected static $protectedStatic1;
		public $public1;
		private /** @noinspection PhpUnusedPrivateFieldInspection */
				$private1;
		protected $protected1;
	}

	class DummyClass2 extends DummyClass1
	{
		public static $publicStatic2;
		private static /** @noinspection PhpUnusedPrivateFieldInspection */
				$privateStatic2;
		protected static $protectedStatic2;
		public $public2;
		private /** @noinspection PhpUnusedPrivateFieldInspection */
				$private2;
		protected $protected2;
	}

	class MockException extends Types\ReflectedException
	{
		function __construct()
		{
			$class          = 'MuhMockException';
			$message        = <<<'s'
This is a dummy exception message.

lololool
s;
			$code           = 'Dummy exception code';
			$file           = '/the/path/to/muh/file';
			$line           = 9000;
			$previous       = null;
			$localVariables = array(
				'lol' => 8,
				'foo' => 'bar',
			);
			$stackTrace     = array(
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

			$memory  = new Memory;
			$null    = $memory->toID( Ref::create() );
			$globals = array(
				new ReflectedGlobal( 'BlahClass', null, 'blahProperty', $null, 'private' ),
				new ReflectedGlobal( null, 'BlahAnotherClass', 'public', $null, null ),
				new ReflectedGlobal( null, null, 'lol global', $null, null ),
				new ReflectedGlobal( 'BlahYetAnotherClass', 'blahMethod', 'lolStatic', $null, null ),
				new ReflectedGlobal( null, null, 'blahVariable', $null, null ),
			);

			$stack  = self::reflectStack( $memory, $stackTrace );
			$locals = self::reflectLocalVariables( $memory, $localVariables );

			parent::__construct( $class, $file, $line, $stack, $globals, $locals, $code, $message, $previous );
		}
	}
}


