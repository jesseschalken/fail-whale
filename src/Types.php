<?php

namespace PrettyPrinter\Introspection
{
    use PrettyPrinter\Utils\Ref;
    use PrettyPrinter\Values;

    interface ExceptionHasFullTrace
    {
        /**
         * @return array
         */
        function getFullTrace();
    }

    interface ExceptionHasLocalVariables
    {
        /**
         * @return array|null
         */
        function getLocalVariables();
    }

    class Introspection
    {
        private $cacheArrayRef = array();
        private $cacheByString = array();
        private $pool;

        function __construct( Values\ValuePool $pool )
        {
            $this->pool = $pool;
        }

        private function introspectCacheArrayRef( \Closure $wrapped, &$reference )
        {
            foreach ( $this->cacheArrayRef as $ref )
                if ( Ref::equal( $reference, $ref[ 1 ] ) )
                    return $ref[ 0 ];

            $id = $this->pool->newEmpty();

            array_unshift( $this->cacheArrayRef, array( $id, &$reference ) );

            $id->fill( $wrapped );

            array_shift( $this->cacheArrayRef );

            return $id;
        }

        private function introspectCacheByString( \Closure $wrapped, $type, $string )
        {
            if ( isset( $this->cacheByString[ $type ][ $string ] ) )
                return $this->cacheByString[ $type ][ $string ];

            $ref = $this->pool->newEmpty();

            $this->cacheByString[ $type ][ $string ] = $ref;

            $ref->fill( $wrapped );

            return $ref;
        }

        /**
         * @param \ReflectionProperty|\ReflectionMethod $property
         *
         * @return string
         */
        function propertyOrMethodAccess( $property )
        {
            return $property->isPrivate() ? 'private' : ( $property->isPublic() ? 'public' : 'protected' );
        }

        /**
         * @param $value
         *
         * @return Values\ValuePoolReference
         */
        function introspectRef( &$value )
        {
            $that = $this;

            if ( is_string( $value ) )
            {
                $f = function () use ( $value ) { return new Values\ValueString( $value ); };

                return $this->introspectCacheByString( $f, 'string', "$value" );
            }
            else if ( is_int( $value ) )
            {
                $f = function () use ( $value ) { return new Values\ValueInt( $value ); };

                return $this->introspectCacheByString( $f, 'int', "$value" );
            }
            else if ( is_bool( $value ) )
            {
                $f = function () use ( $value ) { return new Values\ValueBool( $value ); };

                return $this->introspectCacheByString( $f, 'bool', "$value" );
            }
            else if ( is_null( $value ) )
            {
                $f = function () use ( $value ) { return new Values\ValueNull( $value ); };

                return $this->introspectCacheByString( $f, 'null', "$value" );
            }
            else if ( is_float( $value ) )
            {
                $f = function () use ( $value ) { return new Values\ValueFloat( $value ); };

                return $this->introspectCacheByString( $f, 'float', "$value" );
            }
            else if ( is_array( $value ) )
            {
                $f = function () use ( $that, $value ) { return Values\ValueArray::introspect( $that, $value ); };

                return $this->introspectCacheArrayRef( $f, $value );
            }
            else if ( is_object( $value ) )
            {
                $f = function () use ( $that, $value ) { return Values\ValueObject::introspect( $that, $value ); };

                return $this->introspectCacheByString( $f, 'object', spl_object_hash( $value ) );
            }
            else if ( is_resource( $value ) )
            {
                $f = function () use ( $value ) { return Values\ValueResource::introspect( $value ); };

                return $this->introspectCacheByString( $f, 'resource', "$value" );
            }
            else
            {
                $f = function () use ( $value ) { return new Values\ValueUnknown; };

                return $this->introspectCacheByString( $f, 'unknown', '' );
            }
        }

        function introspectValue( $value )
        {
            return $this->introspectRef( $value );
        }

        function introspectException( \Exception $e )
        {
            $that = $this;
            $f    = function () use ( $that, $e ) { return Values\ValueException::introspect( $that, $e ); };

            return $this->introspectCacheByString( $f, 'exception', spl_object_hash( $e ) );
        }
    }
}

namespace PrettyPrinter\Values
{
    use PrettyPrinter\Introspection\ExceptionHasFullTrace;
    use PrettyPrinter\Introspection\ExceptionHasLocalVariables;
    use PrettyPrinter\Introspection\Introspection;
    use PrettyPrinter\PrettyPrinter;
    use PrettyPrinter\Test\DummyClass1;
    use PrettyPrinter\Test\DummyClass2;
    use PrettyPrinter\Utils\ArrayUtil;
    use PrettyPrinter\Utils\Ref;
    use PrettyPrinter\Utils\Text;

    abstract class Value
    {
        /**
         * @param PrettyPrinter $settings
         *
         * @return Text
         */
        abstract function render( PrettyPrinter $settings );
    }

    class ValueArray extends Value
    {
        static function introspect( Introspection $introspection, array $array )
        {
            $self                = new self;
            $self->isAssociative = ArrayUtil::isAssoc( $array );

            foreach ( $array as $k => &$v )
                $self->keyValuePairs[ ] = ArrayEntry::introspect( $introspection, $k, $v );

            return $self;
        }

        private $isAssociative = false;
        /**
         * @var ArrayEntry[]
         */
        private $keyValuePairs = array();

        private function __construct() { }

        function isAssociative() { return $this->isAssociative; }

        function keyValuePairs() { return $this->keyValuePairs; }

        function render( PrettyPrinter $settings ) { return $settings->renderArray( $this ); }
    }

    class ArrayEntry
    {
        static function introspect( Introspection $introspection, &$k, &$v )
        {
            $self        = new self;
            $self->key   = $introspection->introspectRef( $k );
            $self->value = $introspection->introspectRef( $v );

            return $self;
        }

        /** @var ValuePoolReference */
        private $key;
        /** @var ValuePoolReference */
        private $value;

        private function __construct() { }

        function key() { return $this->key; }

        function value() { return $this->value; }
    }

    class ValueBool extends Value
    {
        private $bool;

        /**
         * @param bool $bool
         */
        function __construct( $bool )
        {
            $this->bool = $bool;
        }

        function render( PrettyPrinter $settings ) { return $settings->text( $this->bool ? 'true' : 'false' ); }
    }

    class ValueException extends Value
    {
        static function introspect( Introspection $i, \Exception $e )
        {
            $self          = self::introspectException( $i, $e );
            $self->globals = Variable::introspectGlobals( $i );

            return $self;
        }

        private static function introspectException( Introspection $i, \Exception $e )
        {
            $self          = new self;
            $self->class   = get_class( $e );
            $self->code    = $e->getCode();
            $self->message = $e->getMessage();
            $self->line    = $e->getLine();
            $self->file    = $e->getFile();

            if ( $e->getPrevious() !== null )
                $self->previous = self::introspectException( $i, $e->getPrevious() );

            if ( $e instanceof ExceptionHasLocalVariables && $e->getLocalVariables() !== null )
            {
                $self->locals = array();

                $locals = $e->getLocalVariables();
                foreach ( $locals as $name => &$value )
                    $self->locals[ ] = Variable::introspect( $i, $name, $value );
            }

            foreach ( $e instanceof ExceptionHasFullTrace ? $e->getFullTrace() : $e->getTrace() as $frame )
                $self->stack[ ] = FunctionCall::introspect( $i, $frame );

            return $self;
        }

        private static function introspectGlobals( Introspection $i )
        {
        }

        static function mock( $param )
        {
            $self          = new self;
            $self->class   = 'MuhMockException';
            $self->message = <<<'s'
This is a dummy exception message.

lololool
s;
            $self->code    = 'Dummy exception code';
            $self->file    = '/the/path/to/muh/file';
            $self->line    = 9000;
            $self->locals  = array( Variable::introspect( $param, 'lol', Ref::create( 8 ) ),
                                    Variable::introspect( $param, 'foo', Ref::create( 'bar' ) ) );

            $self->stack   = FunctionCall::mock( $param );
            $self->globals = Variable::mockGlobals( $param );

            return $self;
        }

        private $class;
        /** @var FunctionCall[] */
        private $stack = array();
        private $locals;
        private $code;
        private $message;
        private $previous;
        private $file;
        private $line;
        /** @var Variable[]|null */
        private $globals;

        private function __construct() { }

        function className() { return $this->class; }

        function code() { return $this->code; }

        function file() { return $this->file; }

        function globals() { return $this->globals; }

        function line() { return $this->line; }

        function locals() { return $this->locals; }

        function message() { return $this->message; }

        function previous() { return $this->previous; }

        function render( PrettyPrinter $settings ) { return $settings->renderExceptionWithGlobals( $this ); }

        function stack() { return $this->stack; }
    }

    class Variable
    {
        /**
         * @param Introspection $i
         *
         * @return self[]
         */
        static function introspectGlobals( Introspection $i )
        {
            $globals = array();

            foreach ( $GLOBALS as $variableName => &$globalValue )
            {
                if ( $variableName !== 'GLOBALS' )
                {
                    $self           = self::introspect( $i, $variableName, $globalValue );
                    $self->isGlobal = true;

                    $globals [ ] = $self;
                }
            }

            foreach ( get_declared_classes() as $class )
            {
                $reflection = new \ReflectionClass( $class );

                foreach ( $reflection->getProperties( \ReflectionProperty::IS_STATIC ) as $property )
                {
                    $property->setAccessible( true );

                    $self           = new self( $property->name, $i->introspectValue( $property->getValue() ) );
                    $self->class    = $property->class;
                    $self->access   = $i->propertyOrMethodAccess( $property );
                    $self->isStatic = true;

                    $globals[ ] = $self;
                }

                foreach ( $reflection->getMethods() as $method )
                {
                    $staticVariables = $method->getStaticVariables();

                    foreach ( $staticVariables as $variableName => &$varValue )
                    {
                        $self           = self::introspect( $i, $variableName, $varValue );
                        $self->class    = $method->class;
                        $self->access   = $i->propertyOrMethodAccess( $method );
                        $self->function = $method->getName();
                        $self->isStatic = $method->isStatic();

                        $globals[ ] = $self;
                    }
                }
            }

            foreach ( get_defined_functions() as $section )
            {
                foreach ( $section as $function )
                {
                    $reflection      = new \ReflectionFunction( $function );
                    $staticVariables = $reflection->getStaticVariables();

                    foreach ( $staticVariables as $propertyName => &$varValue )
                    {
                        $self           = self::introspect( $i, $propertyName, $varValue );
                        $self->function = $function;

                        $globals[ ] = $self;
                    }
                }
            }

            return $globals;
        }

        static function introspect( Introspection $i, $name, &$value )
        {
            return new self( $name, $i->introspectRef( $value ) );
        }

        static function introspectObjectProperties( Introspection $i, $object )
        {
            $properties = array();

            for ( $reflection = new \ReflectionObject( $object );
                  $reflection !== false;
                  $reflection = $reflection->getParentClass() )
            {
                foreach ( $reflection->getProperties() as $property )
                {
                    if ( $property->isStatic() || $property->class !== $reflection->name )
                        continue;

                    $property->setAccessible( true );

                    $value          = $property->getValue( $object );
                    $self           = self::introspect( $i, $property->name, $value );
                    $self->class    = $property->class;
                    $self->access   = $i->propertyOrMethodAccess( $property );
                    $self->isStatic = false;

                    $properties[ ] = $self;
                }
            }

            return $properties;
        }

        static function mockGlobals( Introspection $param )
        {
            //  private static BlahClass::$blahProperty                       = null;
            //  function BlahAnotherClass()::static $public                   = null;
            //  global ${"lol global"}                                        = null;
            //  function BlahYetAnotherClass::blahMethod()::static $lolStatic = null;
            //  global $blahVariable                                          = null;

            $null = $param->introspectValue( null );

            $globals = array();

            $self           = new self( 'blahProperty', $null );
            $self->class    = 'BlahClass';
            $self->access   = 'private';
            $self->isStatic = true;

            $globals[ ] = $self;

            $self           = new self( 'public', $null );
            $self->function = 'BlahAnotherClass';

            $globals[ ] = $self;

            $self           = new self( 'lol global', $null );
            $self->isGlobal = true;

            $globals[ ] = $self;

            $self           = new self( 'lolStatic', $null );
            $self->function = 'blahMethod';
            $self->class    = 'BlahYetAnotherClass';
            $self->isStatic = true;

            $globals[ ] = $self;

            $self           = new self( 'blahVariable', $null );
            $self->isGlobal = true;

            $globals[ ] = $self;

            return $globals;
        }

        private $name;
        private $value;
        private $class;
        private $function;
        private $access;
        private $isGlobal = false;
        private $isStatic = false;

        /**
         * @param string             $name
         * @param ValuePoolReference $value
         */
        private function __construct( $name, ValuePoolReference $value )
        {
            $this->value = $value;
            $this->name  = $name;
        }

        function render( PrettyPrinter $settings )
        {
            if ( $this->class !== null )
            {
                if ( $this->function !== null )
                {
                    $prefix = $settings->text( "function $this->class::$this->function()::static " );
                }
                else if ( $this->isStatic )
                {
                    $prefix = $settings->text( "$this->access static $this->class::" );
                }
                else
                {
                    $prefix = $settings->text( "$this->access " );
                }
            }
            else if ( $this->function !== null )
            {
                $prefix = $settings->text( "function $this->function()::static " );
            }
            else if ( $this->isGlobal )
            {
                $prefix = $settings->text( in_array( $this->name, array( '_POST', '_GET', '_SESSION',
                                                                         '_COOKIE', '_FILES',
                                                                         '_REQUEST', '_ENV',
                                                                         '_SERVER' ), true ) ? '' : 'global ' );
            }
            else
            {
                $prefix = $settings->text();
            }

            return $prefix->appendLines( $settings->renderVariable( $this->name ) );
        }

        function value() { return $this->value; }
    }

    class FunctionCall
    {
        static function introspect( Introspection $i, array $frame )
        {
            $self = new self( $frame[ 'function' ] );

            if ( array_key_exists( 'file', $frame ) )
                $self->file = $frame[ 'file' ];

            if ( array_key_exists( 'line', $frame ) )
                $self->line = $frame[ 'line' ];

            if ( array_key_exists( 'class', $frame ) )
                $self->class = $frame[ 'class' ];

            if ( array_key_exists( 'args', $frame ) )
            {
                $self->args = array();

                foreach ( $frame[ 'args' ] as &$arg )
                    $self->args[ ] = $i->introspectRef( $arg );
            }

            if ( array_key_exists( 'object', $frame ) )
                $self->object = $i->introspectRef( $frame[ 'object' ] );

            if ( array_key_exists( 'type', $frame ) )
                $self->isStatic = $frame[ 'type' ] === '::';

            return $self;
        }

        /**
         * @param Introspection $param
         *
         * @return self[]
         */
        static function mock( Introspection $param )
        {
            $stack = array();

            $self         = new self( 'aFunction' );
            $self->args   = array( $param->introspectValue( new DummyClass2 ) );
            $self->file   = '/path/to/muh/file';
            $self->line   = 1928;
            $self->object = $param->introspectValue( new DummyClass1 );
            $self->class  = 'DummyClass1';

            $stack[ ] = $self;

            $self       = new self( 'aFunction' );
            $self->args = array( $param->introspectValue( new DummyClass2 ) );
            $self->file = '/path/to/muh/file';
            $self->line = 1928;

            $stack[ ] = $self;

            return $stack;
        }

        private $class;
        private $function;
        /** @var ValuePoolReference[]|null */
        private $args;
        /** @var ValuePoolReference|null */
        private $object;
        private $isStatic;
        private $file;
        private $line;

        /**
         * @param string $function
         */
        private function __construct( $function )
        {
            $this->function = $function;
        }

        function location()
        {
            return $this->file === null ? '[internal function]' : "$this->file:$this->line";
        }

        function renderArgs( PrettyPrinter $settings )
        {
            if ( $this->args === null )
                return $settings->text( "( ? )" );

            if ( $this->args === array() )
                return $settings->text( "()" );

            $pretties    = array();
            $isMultiLine = false;

            foreach ( $this->args as $arg )
            {
                $pretty      = $arg->render( $settings );
                $isMultiLine = $isMultiLine || $pretty->count() > 1;
                $pretties[ ] = $pretty;
            }

            $result = $settings->text();

            foreach ( $pretties as $k => $pretty )
            {
                if ( $k !== 0 )
                    $result->append( ', ' );

                if ( $isMultiLine )
                    $result->addLines( $pretty );
                else
                    $result->appendLines( $pretty );
            }

            return $result->wrap( "( ", " )" );
        }

        function render( PrettyPrinter $settings )
        {
            return $this->prefix( $settings )
                        ->append( $this->function )
                        ->appendLines( $this->renderArgs( $settings ) );
        }

        function prefix( PrettyPrinter $settings )
        {
            if ( $this->object !== null )
                return $this->object->render( $settings )->append( '->' );

            if ( $this->class !== null )
                return $settings->text( $this->isStatic ? "$this->class::" : "$this->class->" );

            return $settings->text();
        }
    }

    class ValueFloat extends Value
    {
        private $float;

        /**
         * @param float $float
         */
        function __construct( $float )
        {
            $this->float = $float;
        }

        function render( PrettyPrinter $settings )
        {
            $int = (int) $this->float;

            return $settings->text( "$int" === "$this->float" ? "$this->float.0" : "$this->float" );
        }

        function serialize()
        {
            return is_nan( $this->float ) || is_infinite( $this->float ) ? "$this->float" : $this->float;
        }
    }

    class ValueInt extends Value
    {
        private $int;

        /**
         * @param int $int
         */
        function __construct( $int )
        {
            $this->int = $int;
        }

        function render( PrettyPrinter $settings ) { return $settings->text( "$this->int" ); }
    }

    class ValueNull extends Value
    {
        function render( PrettyPrinter $settings ) { return $settings->text( 'null' ); }
    }

    class ValueObject extends Value
    {
        static function introspect( Introspection $i, $object )
        {
            $self             = new self;
            $self->class      = get_class( $object );
            $self->properties = Variable::introspectObjectProperties( $i, $object );

            return $self;
        }

        private $class;
        private $properties = array();

        private function __construct() { }

        function className() { return $this->class; }

        function properties() { return $this->properties; }

        function render( PrettyPrinter $settings ) { return $settings->renderObject( $this ); }
    }

    class ValuePool
    {
        /** @var Value[] */
        private $cells = array();
        private $nextId = 0;

        function fill( $id, \Closure $wrapped )
        {
            if ( $this->cells[ $id ] === null )
                $this->cells[ $id ] = $wrapped();
        }

        function get( $id ) { return $this->cells[ $id ]; }

        function map() { return $this->cells; }

        function newEmpty()
        {
            $id                 = $this->nextId++;
            $this->cells[ $id ] = null;

            return new ValuePoolReference( $this, $id );
        }
    }

    class ValuePoolReference
    {
        static function deserialize( ValuePool $self, $value )
        {
            return new self( $self, $value );
        }

        private $id;
        /** @var ValuePool */
        private $memory;

        function __construct( ValuePool $memory, $id )
        {
            $this->memory = $memory;
            $this->id     = $id;
        }

        function fill( \Closure $wrapped )
        {
            $this->memory->fill( $this->id, $wrapped );
        }

        function get() { return $this->memory->get( $this->id ); }

        function id() { return $this->id; }

        function pool() { return $this->memory; }

        function render( PrettyPrinter $settings ) { return $this->get()->render( $settings ); }
    }

    class ValueResource extends Value
    {
        /**
         * @param resource $value
         *
         * @return \PrettyPrinter\Values\ValueResource
         */
        static function introspect( $value )
        {
            return new self( get_resource_type( $value ) );
        }

        private $resourceType;

        /**
         * @param string $resourceType
         */
        private function __construct( $resourceType )
        {
            $this->resourceType = $resourceType;
        }

        function render( PrettyPrinter $settings ) { return $settings->text( $this->resourceType ); }

        function resourceType() { return $this->resourceType; }
    }

    class ValueString extends Value
    {
        private $string;

        /**
         * @param string $string
         */
        function __construct( $string ) { $this->string = $string; }

        function render( PrettyPrinter $settings ) { return $settings->renderString( $this->string ); }
    }

    class ValueUnknown extends Value
    {
        function render( PrettyPrinter $settings ) { return $settings->text( 'unknown type' ); }
    }
}
