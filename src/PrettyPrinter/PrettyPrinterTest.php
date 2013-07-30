<?php

namespace PrettyPrinter;

use PrettyPrinter\TypeHandlers\Any;

class PrettyPrinterTest extends \PHPUnit_Framework_TestCase
{
	function testSimpleValues()
	{
		$this->assertPretty( null, "null" );
		$this->assertPretty( false, "false" );
		$this->assertPretty( true, "true" );
		$this->assertPretty( INF, "INF" );
		$this->assertPretty( -INF, "-INF" );
		$this->assertPretty( (float) 0, "0.0" );
		$this->assertPretty( 0, "0" );
		$this->assertPretty( 0.0, "0.0" );
		$this->assertPretty( 1, "1" );
		$this->assertPretty( -1.99, "-1.99" );
		$this->assertPretty( "lol", '"lol"' );
		$this->assertPretty( array(), "array()" );
		$this->assertPretty( array( "foo" ), 'array( "foo" )' );
		$this->assertPretty( array( "foo", "foo" ),
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

		$this->assertPrettyRef( $recursiveArray,
			<<<'s'
#1 array( "recurse" => #1 array(...) )
s
		);
	}

	function testMultiLineString()
	{
		$this->assertPretty( <<<'s'
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
		$this->assertPretty( new Any( new PrettyPrinter ), <<<'s'
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
                                   private $escapeTabsInStrings          = false;
                                   private $splitMultiLineStrings        = true;
                                   private $maxObjectProperties          = INF;
                                   private $maxArrayEntries              = INF;
                                   private $maxStringLength              = INF;
                                   private $showExceptionLocalVariables  = true;
                                   private $showExceptionGlobalVariables = true;
                                   private $showExceptionStackTrace      = true;
                               };
    private $anyHandler      = new PrettyPrinter\TypeHandlers\Any #1 {...};
}
s
		);
	}

	function testClosure()
	{
		$this->assertPretty( function () { }, <<<'s'
new Closure #1 {
}
s
		);
	}

	function testStdClass()
	{
		$object      = new \stdClass;
		$object->foo = 'bar';

		$this->assertPretty( $object, <<<'s'
new stdClass #1 {
    public $foo = "bar";
}
s
 );
	}

	private function assertPretty( $value, $expected )
	{
		$this->assertPrettyRef( $value, $expected );
	}

	private function assertPrettyRef( &$value, $expected )
	{
		$prettyPrinter = new PrettyPrinter;

		$actual = $prettyPrinter->prettyPrintRef( $value );

		$this->assertEquals( $expected, $actual );
	}
}

