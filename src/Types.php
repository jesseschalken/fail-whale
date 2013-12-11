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

        private function introspectNoCache( \Closure $wrapped )
        {
            $ref = $this->pool->newEmpty();
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

        function introspectMockException()
        {
            $that = $this;
            $f    = function () use ( $that ) { return Values\ValueException::mock( $that ); };

            return $this->introspectNoCache( $f );
        }
    }
}

namespace PrettyPrinter\Values
{
    use ErrorHandler\Exception;
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
        static function deserialize( ValuePool $pool, $v )
        {
            if ( is_bool( $v ) )
                return new ValueBool( $v );

            if ( is_string( $v ) )
                return new ValueString( $v );

            if ( is_null( $v ) )
                return new ValueNull;

            if ( is_float( $v ) )
                return new ValueFloat( $v );

            if ( is_int( $v ) )
                return new ValueInt( $v );

            if ( is_array( $v ) )
            {
                switch ( $v[ 'type' ] )
                {
                    case 'object':
                        return ValueObject::deserialize( $pool, $v );
                    case 'float':
                        return ValueFloat::deserialize( $pool, $v );
                    case 'array':
                        return ValueArray::deserialize( $pool, $v );
                    case 'exception':
                        return ValueException::deserialize( $pool, $v );
                    case 'resource':
                        return ValueResource::deserialize( $pool, $v );
                    case 'unknown':
                        return new ValueUnknown;
                }
            }

            throw new Exception( "Invalid JSON value" );
        }

        /**
         * @param PrettyPrinter $settings
         *
         * @return Text
         */
        abstract function render( PrettyPrinter $settings );

        abstract function serialize();
    }

    class ValueArray extends Value
    {
        static function introspect( Introspection $introspection, array $array )
        {
            $self                = new self;
            $self->isAssociative = ArrayUtil::isAssoc( $array );

            foreach ( $array as $k => &$v )
                $self->entries[ ] = ArrayEntry::introspect( $introspection, $k, $v );

            return $self;
        }

        private $isAssociative = false;
        /** @var ArrayEntry[] */
        private $entries = array();

        private function __construct() { }

        function isAssociative() { return $this->isAssociative; }

        function entries() { return $this->entries; }

        function render( PrettyPrinter $settings ) { return $settings->renderArray( $this ); }

        function serialize()
        {
            $entries = array();

            foreach ( $this->entries as $entry )
                $entries[ ] = $entry->serialize();

            return array( 'type'          => 'array',
                          'isAssociative' => $this->isAssociative,
                          'entries'       => $entries );
        }

        static function deserialize( ValuePool $pool, $v )
        {
            $self                = new self;
            $self->isAssociative = $v[ 'isAssociative' ];

            foreach ( $v[ 'entries' ] as $entry )
                $self->entries[ ] = ArrayEntry::deserialize( $pool, $entry );

            return $self;
        }
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

        static function deserialize( ValuePool $pool, $value )
        {
            $self        = new self;
            $self->key   = $pool->deserializeRef( $value[ 'key' ] );
            $self->value = $pool->deserializeRef( $value[ 'value' ] );

            return $self;
        }

        /** @var ValuePoolReference */
        private $key;
        /** @var ValuePoolReference */
        private $value;

        private function __construct() { }

        function key() { return $this->key; }

        function value() { return $this->value; }

        function serialize()
        {
            return array( 'key'   => $this->key->serialize(),
                          'value' => $this->value->serialize() );
        }
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

        function serialize()
        {
            return $this->bool;
        }
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

        static function mock( Introspection $param )
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
        /** @var Variable[]|null */
        private $locals;
        private $code;
        private $message;
        /** @var self|null */
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

        function serialize()
        {
            $stack    = array();
            $locals   = null;
            $previous = $this->previous === null ? null : $this->previous->serialize();
            $globals  = null;

            foreach ( $this->stack as $frame )
                $stack[ ] = $frame->serialize();

            if ( $this->locals !== null )
            {
                $locals = array();

                foreach ( $this->locals as $local )
                    $locals[ ] = $local->serialize();
            }

            if ( $this->globals !== null )
            {
                $globals = array();

                foreach ( $this->globals as $global )
                    $globals[ ] = $global->serialize();
            }

            return array( 'type'     => 'exception',
                          'class'    => $this->class,
                          'stack'    => $stack,
                          'locals'   => $locals,
                          'code'     => $this->code,
                          'message'  => $this->message,
                          'previous' => $previous,
                          'file'     => $this->file,
                          'line'     => $this->line,
                          'globals'  => $globals );
        }

        static function deserialize( ValuePool $pool, $v )
        {
            $self          = new self;
            $self->class   = $v[ 'class' ];
            $self->code    = $v[ 'code' ];
            $self->message = $v[ 'message' ];
            $self->file    = $v[ 'file' ];
            $self->line    = $v[ 'line' ];

            foreach ( $v[ 'stack' ] as $frame )
                $self->stack[ ] = FunctionCall::deserialize( $pool, $frame );

            $self->previous = $v[ 'previous' ] === null ? null : self::deserialize( $pool, $v[ 'previous' ] );

            if ( $v[ 'locals' ] !== null )
            {
                $self->locals = array();

                foreach ( $v[ 'locals' ] as $local )
                    $self->locals[ ] = Variable::deserialize( $pool, $local );
            }

            if ( $v[ 'globals' ] !== null )
            {
                $self->globals = array();

                foreach ( $v[ 'globals' ] as $global )
                    $self->globals[ ] = Variable::deserialize( $pool, $global );
            }

            return $self;
        }
    }

    class Variable
    {
        static function deserialize( ValuePool $pool, $prop )
        {
            $self           = new self( $prop[ 'name' ], $pool->deserializeRef( $prop[ 'value' ] ) );
            $self->function = $prop[ 'function' ];
            $self->access   = $prop[ 'access' ];
            $self->isGlobal = $prop[ 'isGlobal' ];
            $self->isStatic = $prop[ 'isStatic' ];
            $self->class    = $prop[ 'class' ];

            return $self;
        }

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

        function serialize()
        {
            return array( 'name'     => $this->name,
                          'value'    => $this->value->serialize(),
                          'class'    => $this->class,
                          'function' => $this->function,
                          'access'   => $this->access,
                          'isGlobal' => $this->isGlobal,
                          'isStatic' => $this->isStatic );
        }

        function value() { return $this->value; }
    }

    class FunctionCall
    {
        static function deserialize( ValuePool $pool, $frame )
        {
            $self           = new self( $frame[ 'function' ] );
            $self->isStatic = $frame[ 'isStatic' ];
            $self->file     = $frame[ 'file' ];
            $self->line     = $frame[ 'line' ];
            $self->class    = $frame[ 'class' ];
            $self->object   = $frame[ 'object' ] === null ? null : $pool->deserializeRef( $frame[ 'object' ] );

            if ( $frame[ 'args' ] !== null )
            {
                $self->args = array();

                foreach ( $frame[ 'args' ] as $arg )
                    $self->args [ ] = $pool->deserializeRef( $arg );
            }

            return $self;
        }

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

        function serialize()
        {
            $args   = null;
            $object = $this->object === null ? null : $this->object->serialize();

            if ( $this->args !== null )
            {
                $args = array();

                foreach ( $this->args as $arg )
                    $args[ ] = $arg->serialize();
            }

            return array( 'class'    => $this->class,
                          'function' => $this->function,
                          'args'     => $args,
                          'object'   => $object,
                          'isStatic' => $this->isStatic,
                          'file'     => $this->file,
                          'line'     => $this->line );
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
            if ( is_nan( $this->float ) )
                $float = 'nan';
            else if ( $this->float === INF )
                $float = 'inf';
            else if ( $this->float === -INF )
                $float = '-inf';
            else
                $float = $this->float;

            return array( 'type' => 'float', 'value' => $float );
        }

        static function deserialize( ValuePool $pool, $v )
        {
            $float = $v[ 'value' ];

            if ( $float === 'nan' )
                return new self( NAN );

            if ( $float === 'inf' )
                return new self( INF );

            if ( $float === '-inf' )
                return new self( -INF );

            return new self( (float) $float );
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

        function serialize()
        {
            return $this->int;
        }
    }

    class ValueNull extends Value
    {
        function render( PrettyPrinter $settings ) { return $settings->text( 'null' ); }

        function serialize()
        {
            return null;
        }
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
        /** @var Variable[] */
        private $properties = array();

        private function __construct() { }

        function className() { return $this->class; }

        function properties() { return $this->properties; }

        function render( PrettyPrinter $settings ) { return $settings->renderObject( $this ); }

        function serialize()
        {
            $properties = array();

            foreach ( $this->properties as $prop )
                $properties[ ] = $prop->serialize();

            return array( 'type'       => 'object',
                          'class'      => $this->class,
                          'properties' => $properties );
        }

        static function deserialize( ValuePool $pool, $v )
        {
            $self        = new self;
            $self->class = $v[ 'class' ];

            foreach ( $v[ 'properties' ] as $prop )
                $self->properties[ ] = Variable::deserialize( $pool, $prop );

            return $self;
        }
    }

    class ValuePool
    {
        static function deserialize( $value )
        {
            $self         = new self;
            $self->nextId = $value[ 'nextId' ];

            foreach ( $value[ 'cells' ] as $id => $cell )
                $self->cells[ $id ] = Value::deserialize( $self, $cell );

            return $self;
        }

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

        function deserializeRef( $key )
        {
            return ValuePoolReference::deserialize2( $this, $key );
        }

        function serialize()
        {
            $cells = array();

            foreach ( $this->cells as $id => $value )
                $cells[ $id ] = $value->serialize();

            return array( 'nextId' => $this->nextId,
                          'cells'  => $cells );
        }
    }

    class ValuePoolReference extends Value
    {
        static function deserialize2( ValuePool $self, $value )
        {
            return new self( $self, $value );
        }

        static function deserializeWhole( $whole )
        {
            $pool = ValuePool::deserialize( $whole[ 'pool' ] );

            return self::deserialize2( $pool, $whole[ 'root' ] );
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

        function serialize()
        {
            return $this->id;
        }

        function serializeWhole()
        {
            return array( 'root' => $this->serialize(), 'pool' => $this->memory->serialize() );
        }

        function serialuzeUnserialize()
        {
            return self::deserializeWhole( $this->serializeWhole() );
        }
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

        function serialize()
        {
            return array( 'type'         => 'resource',
                          'resourceType' => $this->resourceType );
        }

        static function deserialize( ValuePool $pool, $v )
        {
            return new self( $v[ 'resourceType' ] );
        }
    }

    class ValueString extends Value
    {
        private $string;

        /**
         * @param string $string
         */
        function __construct( $string ) { $this->string = $string; }

        function render( PrettyPrinter $settings ) { return $settings->renderString( $this->string ); }

        function serialize()
        {
            return $this->string;
        }
    }

    class ValueUnknown extends Value
    {
        function render( PrettyPrinter $settings ) { return $settings->text( 'unknown type' ); }

        function serialize()
        {
            return array( 'type' => 'unknown' );
        }
    }
}
