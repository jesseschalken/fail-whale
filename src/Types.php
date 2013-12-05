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
        private $array;
        private $bool;
        private $dummyObject;
        private $exception;
        private $float;
        private $int;
        private $null;
        private $object;
        private $resource;
        private $string;
        private $unknown;

        function __construct( Values\ValuePool $pool )
        {
            $this->array       = new IntrospectionCache( $this, $pool );
            $this->bool        = new IntrospectionCache( $this, $pool );
            $this->dummyObject = new IntrospectionCache( $this, $pool );
            $this->exception   = new IntrospectionCache( $this, $pool );
            $this->float       = new IntrospectionCache( $this, $pool );
            $this->int         = new IntrospectionCache( $this, $pool );
            $this->null        = new IntrospectionCache( $this, $pool );
            $this->object      = new IntrospectionCache( $this, $pool );
            $this->resource    = new IntrospectionCache( $this, $pool );
            $this->string      = new IntrospectionCache( $this, $pool );
            $this->unknown     = new IntrospectionCache( $this, $pool );
        }

        final function wrap( $value ) { return $this->wrapRef( $value ); }

        /**
         * @param mixed $value
         *
         * @return IntrospectionValue
         */
        final function wrapRef( &$value )
        {
            if ( is_string( $value ) )
            {
                return new IntrospectionValueString( $this->string, $value );
            }
            else if ( is_int( $value ) )
            {
                return new IntrospectionValueInt( $this->int, $value );
            }
            else if ( is_bool( $value ) )
            {
                return new IntrospectionValueBool( $this->bool, $value );
            }
            else if ( is_null( $value ) )
            {
                return new IntrospectionValueNull( $this->null );
            }
            else if ( is_float( $value ) )
            {
                return new IntrospectionValueFloat( $this->float, $value );
            }
            else if ( is_array( $value ) )
            {
                return new IntrospectionValueArray( $this->array, $value );
            }
            else if ( is_object( $value ) )
            {
                return new IntrospectionValueObject( $this->object, $value );
            }
            else if ( is_resource( $value ) )
            {
                return new IntrospectionValueResource( $this->resource, $value );
            }
            else
            {
                return new IntrospectionValueUnknown( $this->unknown );
            }
        }

        final function wrapDummyObject( $class )
        {
            return new IntrospectionValueDummyObject( $this->dummyObject, $class );
        }

        final function wrapException( \Exception $e )
        {
            return new IntrospectionValueException( $this->exception, $e );
        }
    }

    class IntrospectionCache
    {
        /**
         * @var Introspection
         */
        private $any;
        private $cacheByReference = array();
        private $cacheByString = array();
        /**
         * @var \PrettyPrinter\Values\ValuePool
         */
        private $pool;

        function __construct( Introspection $any, Values\ValuePool $pool )
        {
            $this->any  = $any;
            $this->pool = $pool;
        }

        function introspect( IntrospectionValue $value )
        {
            $id = $this->pool->newEmpty();
            $id->fill( $value );

            return $id;
        }

        function introspectCacheByReference( IntrospectionValue $array, &$ref )
        {
            foreach ( $this->cacheByReference as $ref )
            {
                if ( Ref::equal( $ref, $ref[ 1 ] ) )
                {
                    return $ref[ 0 ];
                }
            }

            $id = $this->pool->newEmpty();

            array_unshift( $this->cacheByReference, array( $id, &$ref ) );

            $id->fill( $array );

            array_shift( $this->cacheByReference );

            return $id;
        }

        function introspectCacheByString( IntrospectionValue $value, $string )
        {
            if ( array_key_exists( $string, $this->cacheByString ) )
            {
                return $this->cacheByString[ $string ];
            }

            $ref = $this->pool->newEmpty();

            $this->cacheByString[ $string ] = $ref;

            $ref->fill( $value );

            return $ref;
        }

        function wrapDummyObject( $class )
        {
            return $this->any->wrapDummyObject( $class );
        }

        function wrapRef( &$value )
        {
            return $this->any->wrapRef( $value );
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

        private $cache;

        function __construct( IntrospectionCache $cache )
        {
            $this->cache = $cache;
        }

        protected function cache()
        {
            return $this->cache;
        }

        function introspect()
        {
            return $this->cache->introspect( $this );
        }

        /**
         * @return Values\Value
         */
        abstract function introspectImpl();

        protected function wrap( $value ) { return $this->wrapRef( $value ); }

        protected function wrapRef( &$value )
        {
            return $this->cache->wrapRef( $value );
        }

        protected function wrapDummyObject( $class )
        {
            return $this->cache->wrapDummyObject( $class );
        }
    }

    class IntrospectionValueArray extends IntrospectionValue
    {
        /** @var array */
        private $array;

        function __construct( IntrospectionCache $cache, array &$array )
        {
            parent::__construct( $cache );

            $this->array =& $array;
        }

        function introspect()
        {
            return $this->cache()->introspectCacheByReference( $this, $this->array );
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
    }

    class IntrospectionValueBool extends IntrospectionValueCacheByString
    {
        private $bool;

        /**
         * @param IntrospectionCache $cache
         * @param bool               $bool
         */
        function __construct( IntrospectionCache $cache, $bool )
        {
            $this->bool = $bool;

            parent::__construct( $cache );
        }

        function introspectImpl() { return new Values\ValueBool( $this->bool ); }

        function toString() { return "$this->bool"; }
    }

    abstract class IntrospectionValueCacheByString extends IntrospectionValue
    {
        function introspect()
        {
            return $this->cache()->introspectCacheByString( $this, $this->toString() );
        }

        abstract function toString();
    }

    class IntrospectionValueDummyObject extends IntrospectionValueCacheByString
    {
        /**
         * @var string
         */
        private $class;

        function __construct( IntrospectionCache $cache, $class )
        {
            parent::__construct( $cache );

            $this->class = $class;
        }

        function introspectImpl()
        {
            return new Values\ValueObject( $this->class, null );
        }

        function toString()
        {
            return $this->class;
        }
    }

    class IntrospectionValueException extends IntrospectionValueCacheByString
    {
        /**
         * @var \Exception
         */
        private $e;

        function __construct( IntrospectionCache $cache, \Exception $e )
        {
            parent::__construct( $cache );

            $this->e = $e;
        }

        function introspectImpl()
        {
            return $this->introspectException( $this->e, $this->introspectGlobalVariables() );
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
            $code     = $exception->getCode();
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

        /**
         * @param array|null $locals
         *
         * @return Values\ValuePoolReference[]|null
         */
        private function introspectLocalVariables( array $locals = null )
        {
            if ( $locals === null )
            {
                return null;
            }

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
                if ( array_key_exists( 'object', $frame ) )
                {
                    $object = $this->wrapRef( $frame[ 'object' ] )->introspect();
                }
                else if ( array_key_exists( 'class', $frame ) )
                {
                    $object = $this->wrapDummyObject( $frame[ 'class' ] )->introspect();
                }
                else
                {
                    $object = null;
                }

                $args = null;

                if ( array_key_exists( 'args', $frame ) )
                {
                    $args = array();

                    foreach ( $frame[ 'args' ] as &$arg )
                    {
                        $args[ ] = $this->wrapRef( $arg )->introspect();
                    }
                }

                $type     = ArrayUtil::get( $frame, 'type' );
                $function = ArrayUtil::get( $frame, 'function' );
                $file     = ArrayUtil::get( $frame, 'file' );
                $line     = ArrayUtil::get( $frame, 'line' );

                $stackFrames[ ] = new Values\ValueExceptionStackFrame( $type, $function, $object, $args, $file, $line );
            }

            return $stackFrames;
        }

        function toString() { return spl_object_hash( $this->e ); }
    }

    class IntrospectionValueFloat extends IntrospectionValueCacheByString
    {
        private $float;

        /**
         * @param IntrospectionCache $cache
         * @param float              $float
         */
        function __construct( IntrospectionCache $cache, $float )
        {
            parent::__construct( $cache );

            $this->float = $float;
        }

        function introspectImpl() { return new Values\ValueFloat( $this->float ); }

        function toString() { return "$this->float"; }
    }

    class IntrospectionValueInt extends IntrospectionValueCacheByString
    {
        private $int;

        /**
         * @param IntrospectionCache $cache
         * @param int                $int
         */
        function __construct( IntrospectionCache $cache, $int )
        {
            $this->int = $int;

            parent::__construct( $cache );
        }

        function introspectImpl() { return new Values\ValueInt( $this->int ); }

        function toString() { return "$this->int"; }
    }

    class IntrospectionValueNull extends IntrospectionValueCacheByString
    {
        function introspectImpl() { return new Values\ValueNull; }

        function toString() { return ""; }
    }

    class IntrospectionValueObject extends IntrospectionValueCacheByString
    {
        private $object;

        /**
         * @param IntrospectionCache $cache
         * @param object             $object
         */
        function __construct( IntrospectionCache $cache, $object )
        {
            $this->object = $object;

            parent::__construct( $cache );
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
                    {
                        continue;
                    }

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
    }

    class IntrospectionValueResource extends IntrospectionValueCacheByString
    {
        private $resource;

        /**
         * @param IntrospectionCache $cache
         * @param resource           $resource
         */
        function __construct( IntrospectionCache $cache, $resource )
        {
            parent::__construct( $cache );

            $this->resource = $resource;
        }

        function introspectImpl() { return new Values\ValueResource( get_resource_type( $this->resource ) ); }

        function toString() { return "$this->resource"; }
    }

    class IntrospectionValueString extends IntrospectionValueCacheByString
    {
        private $string;

        /**
         * @param IntrospectionCache $cache
         * @param string             $string
         */
        function __construct( IntrospectionCache $cache, $string )
        {
            $this->string = $string;

            parent::__construct( $cache );
        }

        function introspectImpl() { return new Values\ValueString( $this->string ); }

        function toString() { return $this->string; }
    }

    class IntrospectionValueUnknown extends IntrospectionValueCacheByString
    {
        function introspectImpl() { return new Values\ValueUnknown; }

        function toString() { return ''; }
    }
}

namespace PrettyPrinter\Values
{
    use PrettyPrinter\Introspection\IntrospectionValue;
    use PrettyPrinter\PrettyPrinter;
    use PrettyPrinter\Utils\Table;
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

        function render( PrettyPrinter $settings )
        {
            if ( $this->keyValuePairs === array() )
            {
                return new Text( 'array()' );
            }

            $table = new Table;

            foreach ( $this->keyValuePairs as $keyValuePair )
            {
                if ( ( $table->count() + 1 ) > $settings->getMaxArrayEntries() )
                {
                    break;
                }

                $key   = $keyValuePair->key()->render( $settings );
                $value = $keyValuePair->value()->render( $settings );

                if ( $table->count() != count( $this->keyValuePairs ) - 1 )
                {
                    $value->append( ',' );
                }

                $table->addRow( $this->isAssociative ? array( $key, $value->prepend( ' => ' ) ) : array( $value ) );
            }

            $result = $table->render();

            if ( $table->count() < count( $this->keyValuePairs ) )
            {
                $result->addLine( '...' );
            }

            $result->wrap( 'array( ', ' )' );

            return $result;
        }
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

        function render( PrettyPrinter $settings ) { return new Text( $this->bool ? 'true' : 'false' ); }
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
         * @param mixed                       $code
         * @param string                      $message
         * @param ValueException|null         $previous
         */
        function __construct( $class, $file, $line, array $stack, array $globals, array $locals, $code, $message,
                              self $previous = null )
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

        /**
         * @param \PrettyPrinter\PrettyPrinter $settings
         *
         * @return Text
         */
        function render( PrettyPrinter $settings )
        {
            $text = $this->renderWithoutGlobals( $settings );

            if ( $settings->getShowExceptionGlobalVariables() )
            {
                $text->addLine( "global variables:" );
                $text->addLines( $this->renderGlobals( $settings )->indent() );
                $text->addLine();
            }

            return $text;
        }

        private function renderWithoutGlobals( PrettyPrinter $settings )
        {
            $text = new Text;
            $text->addLine( "$this->class $this->code in $this->file:$this->line" );
            $text->addLine();
            $text->addLines( Text::create( $this->message )->indent( 2 ) );
            $text->addLine();

            if ( $this->locals !== null && $settings->getShowExceptionLocalVariables() )
            {
                $text->addLine( "local variables:" );
                $text->addLines( $this->renderLocals( $settings )->indent() );
                $text->addLine();
            }

            if ( $settings->getShowExceptionStackTrace() )
            {
                $text->addLine( "stack trace:" );
                $text->addLines( $this->renderStack( $settings )->indent() );
                $text->addLine();
            }

            if ( $this->previous !== null )
            {
                $text->addLine( "previous exception:" );
                $text->addLines( $this->previous->renderWithoutGlobals( $settings )->indent( 2 ) );
                $text->addLine();
            }

            return $text;
        }

        private function renderGlobals( PrettyPrinter $settings )
        {
            $table = new Table;

            foreach ( $this->globals as $global )
            {
                $table->addRow( array( $global->renderVar( $settings ),
                                       $global->renderValue( $settings )->wrap( ' = ', ';' ) ) );
            }

            return $table->count() > 0 ? $table->render() : new Text( 'none' );
        }

        private function renderLocals( PrettyPrinter $settings )
        {
            $table = new Table;

            foreach ( $this->locals as $name => $value )
            {
                $table->addRow( array( ValueString::renderVariable( $settings, $name ),
                                       $value->render( $settings )->wrap( ' = ', ';' ) ) );
            }

            return $table->count() > 0 ? $table->render() : new Text( 'none' );
        }

        private function renderStack( PrettyPrinter $settings )
        {
            $text = new Text;
            $i    = 1;

            foreach ( $this->stack as $frame )
            {
                $text->addLines( $frame->render( $i, $settings ) );
                $text->addLine();
                $i++;
            }

            $text->addLine( "#$i {main}" );

            return $text;
        }
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

        function renderValue( PrettyPrinter $settings )
        {
            return $this->value->render( $settings );
        }

        function renderVar( PrettyPrinter $settings )
        {
            return $this->prefix()->appendLines( ValueString::renderVariable( $settings, $this->name ) );
        }

        private function prefix()
        {
            if ( $this->class !== null && $this->function !== null )
            {
                return new Text( "function $this->class::$this->function()::static " );
            }

            if ( $this->class !== null )
            {
                return new Text( "$this->access static $this->class::" );
            }

            if ( $this->function !== null )
            {
                return new Text( "function $this->function()::static " );
            }

            $superGlobals = array( '_POST', '_GET', '_SESSION', '_COOKIE', '_FILES', '_REQUEST', '_ENV', '_SERVER' );

            return new Text( in_array( $this->name, $superGlobals, true ) ? '' : 'global ' );
        }
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

        /**
         * @param int           $i
         * @param PrettyPrinter $settings
         *
         * @return Text
         */
        function render( $i, PrettyPrinter $settings )
        {
            $text = new Text;
            $text->addLine( "#$i $this->file:$this->line" );
            $text->addLines( $this->renderFunctionCall( $settings )->indent( 3 ) );

            return $text;
        }

        private function renderFunctionCall( PrettyPrinter $settings )
        {
            $text = new Text;
            $text->appendLines( $this->renderObject( $settings ) );
            $text->append( "$this->type" );
            $text->append( "$this->function" );
            $text->appendLines( $this->renderArgs( $settings ) );
            $text->append( ';' );

            return $text;
        }

        private function renderObject( PrettyPrinter $settings )
        {
            if ( $this->object === null )
            {
                return new Text();
            }

            return $this->object->render( $settings );
        }

        private function renderArgs( PrettyPrinter $settings )
        {
            if ( $this->args === null )
            {
                return new Text( '( ? )' );
            }

            if ( $this->args === array() )
            {
                return new Text( '()' );
            }

            $pretties    = array();
            $isMultiLine = false;
            $result      = new Text;

            foreach ( $this->args as $arg )
            {
                $pretty      = $arg->render( $settings );
                $isMultiLine = $isMultiLine || $pretty->count() > 1;
                $pretties[ ] = $pretty;
            }

            foreach ( $pretties as $k => $pretty )
            {
                if ( $k !== 0 )
                {
                    $result->append( ', ' );
                }

                if ( $isMultiLine )
                {
                    $result->addLines( $pretty );
                }
                else
                {
                    $result->appendLines( $pretty );
                }
            }

            return $result->wrap( '( ', ' )' );
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

            return new Text( "$int" === "$this->float" ? "$this->float.0" : "$this->float" );
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

        function render( PrettyPrinter $settings ) { return new Text( "$this->int" ); }
    }

    class ValueNull extends Value
    {
        function render( PrettyPrinter $settings ) { return new Text( 'null' ); }
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

        function render( PrettyPrinter $settings )
        {
            if ( $this->properties === null )
            {
                return new Text( "new $this->class { ? }" );
            }

            $table = new Table;

            foreach ( $this->properties as $property )
            {
                if ( ( $table->count() + 1 ) > $settings->getMaxObjectProperties() )
                {
                    break;
                }

                $value  = $property->value();
                $name   = $property->name();
                $access = $property->access();

                $table->addRow( array( ValueString::renderVariable( $settings, $name )->prepend( "$access " ),
                                       $value->render( $settings )->wrap( ' = ', ';' ) ) );
            }

            $result = $table->render();

            if ( $table->count() < count( $this->properties ) )
            {
                $result->addLine( '...' );
            }

            return $result->indent( 2 )->wrapLines( "new $this->class {", "}" );
        }
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
            if ( !array_key_exists( $id, $this->cells ) )
            {
                $this->cells[ $id ] = $wrapped->introspectImpl();
            }
        }

        function get( $id ) { return $this->cells[ $id ]; }

        function newEmpty() { return new ValuePoolReference( $this, $this->nextId++ ); }
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

        function id() { return $this->id; }

        function render( PrettyPrinter $settings )
        {
            return $this->get()->render( $settings );
        }

        function get()
        {
            return $this->memory->get( $this->id );
        }
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

        function render( PrettyPrinter $settings )
        {
            return new Text( $this->resourceType );
        }
    }

    class ValueString extends Value
    {
        /**
         * @param PrettyPrinter $settings
         * @param string        $name
         *
         * @return Text
         */
        static function renderVariable( PrettyPrinter $settings, $name )
        {
            if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name ) )
            {
                return new Text( "$$name" );
            }

            return self::renderString( $settings, $name )->wrap( '${', '}' );
        }

        static function renderString( PrettyPrinter $settings, $string )
        {
            $escapeTabs    = $settings->getEscapeTabsInStrings();
            $splitNewlines = $settings->getSplitMultiLineStrings();

            $characterEscapeCache = array( "\\" => '\\\\',
                                           "\$" => '\$',
                                           "\r" => '\r',
                                           "\v" => '\v',
                                           "\f" => '\f',
                                           "\"" => '\"',
                                           "\t" => $escapeTabs ? '\t' : "\t",
                                           "\n" => $splitNewlines ? "\\n\" .\n\"" : '\n' );

            $escaped = '';
            $length  = min( strlen( $string ), $settings->getMaxStringLength() );

            for ( $i = 0; $i < $length; $i++ )
            {
                $char        = $string[ $i ];
                $charEscaped =& $characterEscapeCache[ $char ];

                if ( !isset( $charEscaped ) )
                {
                    $ord         = ord( $char );
                    $charEscaped = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr( '00' . dechex( $ord ), -2 );
                }

                $escaped .= $charEscaped;
            }

            return new Text( "\"$escaped" . ( strlen( $string ) > $length ? '...' : '"' ) );
        }

        private $string;

        /**
         * @param string $string
         */
        function __construct( $string )
        {
            $this->string = $string;
        }

        function render( PrettyPrinter $settings )
        {
            return self::renderString( $settings, $this->string );
        }
    }

    class ValueUnknown extends Value
    {
        function render( PrettyPrinter $settings ) { return new Text( 'unknown type' ); }
    }
}

