<?php

namespace PrettyPrinter\Introspection
{
    use PrettyPrinter\HasFullTrace;
    use PrettyPrinter\HasLocalVariables;
    use PrettyPrinter\Utils\ArrayUtil;
    use PrettyPrinter\Utils\Ref;
    use PrettyPrinter\Values;

    class Introspection
    {
        private $cacheArrayRef = array();
        private $cacheByString = array();
        private $pool;

        function __construct( Values\ValuePool $pool )
        {
            $this->pool = $pool;
        }

        function introspectCacheArrayRef( IntrospectionValueArray $array )
        {
            $reference =& $array->reference();

            foreach ( $this->cacheArrayRef as $ref )
            {
                if ( Ref::equal( $reference, $ref[ 1 ] ) )
                {
                    return $ref[ 0 ];
                }
            }

            $id = $this->pool->newEmpty();

            array_unshift( $this->cacheArrayRef, array( $id, &$reference ) );

            $id->fill( $array );

            array_shift( $this->cacheArrayRef );

            return $id;
        }

        function introspectCacheByString( IntrospectionValueCacheByString $value )
        {
            $string = $value->toString();
            $type   = $value->type();

            if ( isset( $this->cacheByString[ $type ][ $string ] ) )
            {
                return $this->cacheByString[ $type ][ $string ];
            }

            $ref = $this->pool->newEmpty();

            $this->cacheByString[ $type ][ $string ] = $ref;

            $ref->fill( $value );

            return $ref;
        }

        function introspectNoCache( IntrospectionValue $value )
        {
            $id = $this->pool->newEmpty();
            $id->fill( $value );

            return $id;
        }

        /**
         * @param mixed $value
         *
         * @return IntrospectionValue
         */
        final function wrap( $value ) { return $this->wrapRef( $value ); }

        /**
         * @param string $class
         *
         * @return IntrospectionValue
         */
        final function wrapDummyObject( $class )
        {
            return new IntrospectionValueDummyObject( $this, $class );
        }

        /**
         * @param \Exception $e
         *
         * @return IntrospectionValue
         */
        final function wrapException( \Exception $e )
        {
            return new IntrospectionValueException( $this, $e );
        }

        /**
         * @param mixed $value
         *
         * @return IntrospectionValue
         */
        final function wrapRef( &$value )
        {
            if ( is_string( $value ) )
                return new IntrospectionValueString( $this, $value );

            if ( is_int( $value ) )
                return new IntrospectionValueInt( $this, $value );

            if ( is_bool( $value ) )
                return new IntrospectionValueBool( $this, $value );

            if ( is_null( $value ) )
                return new IntrospectionValueNull( $this );

            if ( is_float( $value ) )
                return new IntrospectionValueFloat( $this, $value );

            if ( is_array( $value ) )
                return new IntrospectionValueArray( $this, $value );

            if ( is_object( $value ) )
                return new IntrospectionValueObject( $this, $value );

            if ( is_resource( $value ) )
                return new IntrospectionValueResource( $this, $value );

            return new IntrospectionValueUnknown( $this );
        }
    }

    abstract class IntrospectionValue
    {
        /**
         * @param \ReflectionProperty|\ReflectionMethod $property
         *
         * @return string
         */
        protected static function propertyOrMethodAccess( $property )
        {
            return $property->isPrivate() ? 'private' : ( $property->isPublic() ? 'public' : 'protected' );
        }

        private $introspection;

        function __construct( Introspection $introspection )
        {
            $this->introspection = $introspection;
        }

        function introspect()
        {
            return $this->introspection->introspectNoCache( $this );
        }

        /**
         * @return Values\Value
         */
        abstract function introspectImpl();

        protected function introspection() { return $this->introspection; }

        protected function wrap( $value )
        {
            return $this->introspection->wrapRef( $value );
        }

        protected function wrapDummyObject( $class )
        {
            return $this->introspection->wrapDummyObject( $class );
        }

        protected function wrapRef( &$value )
        {
            return $this->introspection->wrapRef( $value );
        }
    }

    class IntrospectionValueArray extends IntrospectionValue
    {
        /** @var array */
        private $array;

        function __construct( Introspection $introspection, array &$array )
        {
            parent::__construct( $introspection );

            $this->array =& $array;
        }

        function introspect()
        {
            return $this->introspection()->introspectCacheArrayRef( $this, $this->array );
        }

        function introspectImpl()
        {
            $keyValuePairs = array();

            foreach ( $this->array as $k => &$v )
            {
                $keyValuePairs[ ] = new Values\ValueArrayKeyValuePair( $this->wrapRef( $k )->introspect(),
                                                                       $this->wrapRef( $v )->introspect() );
            }

            return new Values\ValueArray( ArrayUtil::isAssoc( $this->array ), $keyValuePairs );
        }

        function &reference() { return $this->array; }
    }

    class IntrospectionValueBool extends IntrospectionValueCacheByString
    {
        private $bool;

        /**
         * @param Introspection $introspection
         * @param bool          $bool
         */
        function __construct( Introspection $introspection, $bool )
        {
            $this->bool = $bool;

            parent::__construct( $introspection );
        }

        function introspectImpl() { return new Values\ValueBool( $this->bool ); }

        function toString() { return "$this->bool"; }

        function type() { return 'bool'; }
    }

    abstract class IntrospectionValueCacheByString extends IntrospectionValue
    {
        function introspect() { return $this->introspection()->introspectCacheByString( $this ); }

        abstract function toString();

        abstract function type();
    }

    class IntrospectionValueDummyObject extends IntrospectionValueCacheByString
    {
        /** @var string */
        private $class;

        function __construct( Introspection $introspection, $class )
        {
            parent::__construct( $introspection );

            $this->class = $class;
        }

        function introspectImpl() { return new Values\ValueObject( $this->class, null ); }

        function toString() { return $this->class; }

        function type() { return 'dummy object'; }
    }

    class IntrospectionValueException extends IntrospectionValueCacheByString
    {
        /**
         * @var \Exception
         */
        private $e;

        function __construct( Introspection $introspection, \Exception $e )
        {
            parent::__construct( $introspection );

            $this->e = $e;
        }

        private function introspectException( \Exception $exception, array $globals )
        {
            $locals = $exception instanceof HasLocalVariables ? $exception->getLocalVariables() : null;
            $stack  = $exception instanceof HasFullTrace ? $exception->getFullTrace() : $exception->getTrace();

            $locals   = $this->introspectLocalVariables( $locals );
            $stack    = $this->introspectStack( $stack );
            $previous = $exception->getPrevious();
            $previous = $previous === null ? null : $this->introspectException( $previous, $globals );
            $class    = get_class( $exception );
            $file     = $exception->getFile();
            $line     = $exception->getLine();
            $code     = "{$exception->getCode()}";
            $message  = $exception->getMessage();

            return new Values\ValueException( $class, $file, $line, $stack, $globals,
                                              $locals, $code, $message, $previous );
        }

        /**
         * @return Values\ValueExceptionGlobalState[]
         */
        private function introspectGlobalVariables()
        {
            $globals = array();

            foreach ( $GLOBALS as $name => &$globalValue )
            {
                if ( $name !== 'GLOBALS' )
                {
                    $value = $this->wrapRef( $globalValue )->introspect();

                    $globals[ ] = new Values\ValueExceptionGlobalState( null, null, $name, $value, null );
                }
            }

            foreach ( get_declared_classes() as $class )
            {
                $reflection = new \ReflectionClass( $class );

                foreach ( $reflection->getProperties( \ReflectionProperty::IS_STATIC ) as $property )
                {
                    $property->setAccessible( true );

                    $value  = $this->wrap( $property->getValue() )->introspect();
                    $access = self::propertyOrMethodAccess( $property );
                    $class  = $property->class;
                    $name   = $property->name;

                    $globals[ ] = new Values\ValueExceptionGlobalState( $class, null, $name, $value, $access );
                }

                foreach ( $reflection->getMethods() as $method )
                {
                    $staticVariables = $method->getStaticVariables();

                    foreach ( $staticVariables as $name => &$varValue )
                    {
                        $value    = $this->wrapRef( $varValue )->introspect();
                        $class    = $method->class;
                        $function = $method->getName();

                        $globals[ ] = new Values\ValueExceptionGlobalState( $class, $function, $name, $value, null );
                    }
                }
            }

            foreach ( get_defined_functions() as $section )
            {
                foreach ( $section as $function )
                {
                    $reflection      = new \ReflectionFunction( $function );
                    $staticVariables = $reflection->getStaticVariables();

                    foreach ( $staticVariables as $name => &$varValue )
                    {
                        $value    = $this->wrapRef( $varValue )->introspect();
                        $function = $reflection->name;

                        $globals[ ] = new Values\ValueExceptionGlobalState( null, $function, $name, $value, null );
                    }
                }
            }

            return $globals;
        }

        function introspectImpl()
        {
            return $this->introspectException( $this->e, $this->introspectGlobalVariables() );
        }

        /**
         * @param array|null $locals
         *
         * @return Values\ValuePoolReference[]|null
         */
        private function introspectLocalVariables( array $locals = null )
        {
            if ( $locals === null )
                return null;

            $reflected = array();

            foreach ( $locals as $k => &$v )
            {
                $reflected[ $k ] = $this->wrapRef( $v )->introspect();
            }

            return $reflected;
        }

        /**
         * @param array[] $trace
         *
         * @return Values\ValueExceptionStackFrame[]
         */
        private function introspectStack( array $trace )
        {
            $stackFrames = array();

            foreach ( $trace as $frame )
            {
                $object   = null;
                $args     = null;
                $type     = ArrayUtil::get( $frame, 'type' );
                $function = ArrayUtil::get( $frame, 'function' );
                $file     = ArrayUtil::get( $frame, 'file' );
                $line     = ArrayUtil::get( $frame, 'line' );

                if ( array_key_exists( 'object', $frame ) )
                {
                    $object = $this->wrapRef( $frame[ 'object' ] )->introspect();
                }
                else if ( array_key_exists( 'class', $frame ) )
                {
                    $object = $this->wrapDummyObject( $frame[ 'class' ] )->introspect();
                }

                if ( array_key_exists( 'args', $frame ) )
                {
                    $args = array();

                    foreach ( $frame[ 'args' ] as &$arg )
                    {
                        $args[ ] = $this->wrapRef( $arg )->introspect();
                    }
                }

                $stackFrames[ ] = new Values\ValueExceptionStackFrame( $type, $function, $object, $args, $file, $line );
            }

            return $stackFrames;
        }

        function toString() { return spl_object_hash( $this->e ); }

        function type() { return 'exception'; }
    }

    class IntrospectionValueFloat extends IntrospectionValueCacheByString
    {
        private $float;

        /**
         * @param Introspection $introspection
         * @param float         $float
         */
        function __construct( Introspection $introspection, $float )
        {
            parent::__construct( $introspection );

            $this->float = $float;
        }

        function introspectImpl() { return new Values\ValueFloat( $this->float ); }

        function toString() { return "$this->float"; }

        function type() { return 'float'; }
    }

    class IntrospectionValueInt extends IntrospectionValueCacheByString
    {
        private $int;

        /**
         * @param Introspection $introspection
         * @param int           $int
         */
        function __construct( Introspection $introspection, $int )
        {
            $this->int = $int;

            parent::__construct( $introspection );
        }

        function introspectImpl() { return new Values\ValueInt( $this->int ); }

        function toString() { return "$this->int"; }

        function type() { return 'int'; }
    }

    class IntrospectionValueNull extends IntrospectionValueCacheByString
    {
        function introspectImpl() { return new Values\ValueNull; }

        function toString() { return ""; }

        function type() { return 'null'; }
    }

    class IntrospectionValueObject extends IntrospectionValueCacheByString
    {
        private $object;

        /**
         * @param Introspection $introspection
         * @param object        $object
         */
        function __construct( Introspection $introspection, $object )
        {
            $this->object = $object;

            parent::__construct( $introspection );
        }

        function introspectImpl()
        {
            $properties = array();

            for ( $reflection = new \ReflectionObject( $this->object );
                  $reflection !== false;
                  $reflection = $reflection->getParentClass() )
            {
                foreach ( $reflection->getProperties() as $property )
                {
                    if ( $property->isStatic() || $property->class !== $reflection->name )
                        continue;

                    $property->setAccessible( true );

                    $access        = self::propertyOrMethodAccess( $property );
                    $value         = $this->wrap( $property->getValue( $this->object ) )->introspect();
                    $properties[ ] = new Values\ValueObjectProperty( $value, $property->name,
                                                                     $access, $property->class );
                }
            }

            return new Values\ValueObject( get_class( $this->object ), $properties );
        }

        function toString() { return spl_object_hash( $this->object ); }

        function type() { return 'object'; }
    }

    class IntrospectionValueResource extends IntrospectionValueCacheByString
    {
        private $resource;

        /**
         * @param Introspection $introspection
         * @param resource      $resource
         */
        function __construct( Introspection $introspection, $resource )
        {
            parent::__construct( $introspection );

            $this->resource = $resource;
        }

        function introspectImpl() { return new Values\ValueResource( get_resource_type( $this->resource ) ); }

        function toString() { return "$this->resource"; }

        function type() { return 'resource'; }
    }

    class IntrospectionValueString extends IntrospectionValueCacheByString
    {
        private $string;

        /**
         * @param Introspection $introspection
         * @param string        $string
         */
        function __construct( Introspection $introspection, $string )
        {
            $this->string = $string;

            parent::__construct( $introspection );
        }

        function introspectImpl() { return new Values\ValueString( $this->string ); }

        function toString() { return $this->string; }

        function type() { return 'string'; }
    }

    class IntrospectionValueUnknown extends IntrospectionValueCacheByString
    {
        function introspectImpl() { return new Values\ValueUnknown; }

        function toString() { return ''; }

        function type() { return 'unknown'; }
    }
}

namespace PrettyPrinter\Values
{
    use PrettyPrinter\Introspection\IntrospectionValue;
    use PrettyPrinter\PrettyPrinter;
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
        private $isAssociative, $keyValuePairs;

        /**
         * @param bool                     $isAssociative
         * @param ValueArrayKeyValuePair[] $keyValuePairs
         */
        function __construct( $isAssociative, array $keyValuePairs )
        {
            $this->isAssociative = $isAssociative;
            $this->keyValuePairs = $keyValuePairs;
        }

        function isAssociative() { return $this->isAssociative; }

        function keyValuePairs() { return $this->keyValuePairs; }

        function render( PrettyPrinter $settings ) { return $settings->renderArray( $this ); }
    }

    class ValueArrayKeyValuePair
    {
        private $key, $value;

        function __construct( ValuePoolReference $key, ValuePoolReference $value )
        {
            $this->key   = $key;
            $this->value = $value;
        }

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

        function render( PrettyPrinter $settings ) { return $settings->renderBool( $this->bool ); }
    }

    class ValueException extends Value
    {
        private $class, $file, $line, $stack, $globals, $locals, $code, $message, $previous;

        /**
         * @param string                      $class
         * @param string                      $file
         * @param int                         $line
         * @param ValueExceptionStackFrame[]  $stack
         * @param ValueExceptionGlobalState[] $globals
         * @param ValuePoolReference[]|null   $locals
         * @param string                      $code
         * @param string                      $message
         * @param ValueException|null         $previous
         */
        function __construct( $class, $file, $line, array $stack, array $globals,
                              array $locals, $code, $message, self $previous = null )
        {
            $this->class    = $class;
            $this->file     = $file;
            $this->line     = $line;
            $this->stack    = $stack;
            $this->globals  = $globals;
            $this->locals   = $locals;
            $this->code     = $code;
            $this->message  = $message;
            $this->previous = $previous;
        }

        function className() { return $this->class; }

        function code() { return $this->code; }

        function file() { return $this->file; }

        function globals() { return $this->globals; }

        function line() { return $this->line; }

        function locals() { return $this->locals; }

        function message() { return $this->message; }

        function previous() { return $this->previous; }

        function render( PrettyPrinter $settings ) { return $settings->renderException( $this ); }

        function stack() { return $this->stack; }
    }

    class ValueExceptionGlobalState
    {
        private $class, $function, $name, $value, $access;

        /**
         * @param string|null        $class
         * @param string|null        $function
         * @param string             $name
         * @param ValuePoolReference $value
         * @param string|null        $access
         */
        function __construct( $class, $function, $name, $value, $access )
        {
            $this->class    = $class;
            $this->function = $function;
            $this->name     = $name;
            $this->value    = $value;
            $this->access   = $access;
        }

        function access() { return $this->access; }

        function className() { return $this->class; }

        function functionName() { return $this->function; }

        function value() { return $this->value; }

        function variableName() { return $this->name; }
    }

    class ValueExceptionStackFrame
    {
        private $type, $function, $object, $args, $file, $line;

        /**
         * @param string|null               $type
         * @param string|null               $function
         * @param ValuePoolReference|null   $object
         * @param ValuePoolReference[]|null $args
         * @param string|null               $file
         * @param int|null                  $line
         *
         * @internal param null|string $class
         */
        function __construct( $type, $function, $object, $args, $file, $line )
        {
            $this->type     = $type;
            $this->function = $function;
            $this->object   = $object;
            $this->args     = $args;
            $this->file     = $file;
            $this->line     = $line;
        }

        function args() { return $this->args; }

        function file() { return $this->file; }

        function functionName() { return $this->function; }

        function line() { return $this->line; }

        function object() { return $this->object; }

        function type() { return $this->type; }
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

        function render( PrettyPrinter $settings ) { return $settings->renderFloat( $this->float ); }
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

        function render( PrettyPrinter $settings ) { return $settings->renderInt( $this->int ); }
    }

    class ValueNull extends Value
    {
        function render( PrettyPrinter $settings ) { return $settings->renderNull(); }
    }

    class ValueObject extends Value
    {
        private $class, $properties;

        /**
         * @param string                     $class
         * @param ValueObjectProperty[]|null $properties
         */
        function __construct( $class, array $properties = null )
        {
            $this->class      = $class;
            $this->properties = $properties;
        }

        function getClass() { return $this->class; }

        function getProperties() { return $this->properties; }

        function render( PrettyPrinter $settings ) { return $settings->renderObject( $this ); }
    }

    class ValueObjectProperty
    {
        private $value, $name, $access, $class;

        /**
         * @param ValuePoolReference $value
         * @param string             $name
         * @param string             $access
         * @param string             $class
         */
        function __construct( ValuePoolReference $value, $name, $access, $class )
        {
            $this->value  = $value;
            $this->name   = $name;
            $this->access = $access;
            $this->class  = $class;
        }

        function access() { return $this->access; }

        function className() { return $this->class; }

        function name() { return $this->name; }

        function value() { return $this->value; }
    }

    class ValuePool
    {
        /** @var Value[] */
        private $cells = array();
        private $nextId = 0;

        function fill( $id, IntrospectionValue $wrapped )
        {
            if ( $this->cells[ $id ] === null )
                $this->cells[ $id ] = $wrapped->introspectImpl();
        }

        function get( $id ) { return $this->cells[ $id ]; }

        function newEmpty()
        {
            $id                 = $this->nextId++;
            $this->cells[ $id ] = null;

            return new ValuePoolReference( $this, $id );
        }
    }

    class ValuePoolReference
    {
        private $memory, $id;

        function __construct( ValuePool $memory, $id )
        {
            $this->memory = $memory;
            $this->id     = $id;
        }

        /**
         * @param IntrospectionValue $wrapped
         */
        function fill( IntrospectionValue $wrapped )
        {
            $this->memory->fill( $this->id, $wrapped );
        }

        function get() { return $this->memory->get( $this->id ); }

        function id() { return $this->id; }

        function render( PrettyPrinter $settings ) { return $settings->renderReference( $this ); }
    }

    class ValueResource extends Value
    {
        private $resourceType;

        /**
         * @param string $resourceType
         */
        function __construct( $resourceType )
        {
            $this->resourceType = $resourceType;
        }

        function render( PrettyPrinter $settings ) { return new Text( $this->resourceType ); }
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
        function render( PrettyPrinter $settings ) { return $settings->renderUnknown(); }
    }
}

