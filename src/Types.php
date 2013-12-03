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
        /** @var Type[] */
        private $types;
        private $exception;

        function __construct( Values\ValuePool $pool )
        {
            $this->types = array( 'boolean'      => new TypeBool( $this, $pool ),
                                  'integer'      => new TypeInt( $this, $pool ),
                                  'double'       => new TypeFloat( $this, $pool ),
                                  'string'       => new TypeString( $this, $pool ),
                                  'array'        => new TypeArray( $this, $pool ),
                                  'object'       => new TypeObject( $this, $pool ),
                                  'resource'     => new TypeResource( $this, $pool ),
                                  'NULL'         => new TypeNull( $this, $pool ),
                                  'unknown type' => new TypeUnknown( $this, $pool ) );

            $this->exception = new TypeException( $this, $pool );
        }

        final function addToPool( &$value )
        {
            return $this->types[ gettype( $value ) ]->addToPool( $value );
        }

        final function addExceptionToPool( \Exception $e )
        {
            return $this->exception->addToPool( $e );
        }
    }

    abstract class Type
    {
        private $any, $pool;

        function __construct( Introspection $any, Values\ValuePool $pool )
        {
            $this->any  = $any;
            $this->pool = $pool;
        }

        final function addToPool( &$value )
        {
            $reference = $this->locateReference( $value );

            if ( $reference->isEmpty() )
                $reference->set( $this->introspect( $value ) );

            return $reference;
        }

        /**
         * @param $value
         *
         * @return Values\ValuePoolReference
         */
        protected function locateReference( &$value ) { return $this->pool->newReference(); }

        protected final function reference( &$value ) { return $this->any->addToPool( $value ); }

        /**
         * @param mixed $value
         *
         * @return Values\Value
         */
        abstract protected function introspect( $value );
    }

    abstract class TypeCaching extends Type
    {
        private $cache = array();

        protected abstract function toString( $value );

        protected final function locateReference( &$value )
        {
            $string = $this->toString( $value );

            if ( isset( $this->cache[ $string ] ) )
                return $this->cache[ $string ];

            $id = parent::locateReference( $value );

            $this->cache[ $string ] = $id;

            return $id;
        }
    }

    class TypeUnknown extends TypeCaching
    {
        protected function toString( $value ) { return ''; }

        protected function introspect( $value ) { return new Values\ValueUnknown; }
    }

    abstract class TypeScalar extends TypeCaching
    {
        protected function toString( $value ) { return "$value"; }
    }

    class TypeBool extends TypeScalar
    {
        protected function introspect( $value ) { return new Values\ValueBool( $value ); }
    }

    class TypeString extends TypeScalar
    {
        protected function introspect( $value ) { return new Values\ValueString( $value ); }
    }

    class TypeInt extends TypeScalar
    {
        protected function introspect( $value ) { return new Values\ValueInt( $value ); }
    }

    class TypeFloat extends TypeScalar
    {
        protected function introspect( $value ) { return new Values\ValueFloat( $value ); }
    }

    class TypeResource extends TypeScalar
    {
        protected function introspect( $value ) { return new Values\ValueResource( get_resource_type( $value ) ); }
    }

    class TypeNull extends TypeScalar
    {
        protected function introspect( $value ) { return new Values\ValueNull; }
    }

    class TypeArray extends Type
    {
        private $references = array();

        protected final function locateReference( &$value )
        {
            foreach ( $this->references as $array )
                if ( Ref::equal( $value, $array[ 'ref' ] ) )
                    return $array[ 'id' ];

            $id = parent::locateReference( $value );

            $this->references[ ] = array( 'id' => $id, 'ref' => &$value );

            return $id;
        }

        protected function introspect( $value )
        {
            $keyValuePairs = array();

            foreach ( $value as $k => &$v )
            {
                $keyValuePairs[ ] = new Values\ValueArrayKeyValuePair( $this->reference( $k ),
                                                                       $this->reference( $v ) );
            }

            return new Values\ValueArray( ArrayUtil::isAssoc( $value ), $keyValuePairs );
        }
    }

    class TypeObject extends TypeCaching
    {
        protected function toString( $value ) { return spl_object_hash( $value ); }

        protected function introspect( $object )
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

                    $access        = TypeException::propertyOrMethodAccess( $property );
                    $value         = $this->reference( Ref::create( $property->getValue( $object ) ) );
                    $properties[ ] = new Values\ValueObjectProperty( $value, $property->name,
                                                                     $access, $property->class );
                }
            }

            return new Values\ValueObject( get_class( $object ), $properties );
        }
    }

    class TypeException extends TypeCaching
    {
        /**
         * @param \ReflectionProperty|\ReflectionMethod $property
         *
         * @return string
         */
        static function propertyOrMethodAccess( $property )
        {
            return $property->isPrivate() ? 'private' : ( $property->isPublic() ? 'public' : 'protected' );
        }

        protected function toString( $value )
        {
            return spl_object_hash( $value );
        }

        /**
         * @param \Exception $value
         *
         * @return Values\Value
         */
        protected function introspect( $value )
        {
            return $this->introspectException( $value, $this->introspectGlobalVariables() );
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
         * @param array[] $trace
         *
         * @return Values\ValueExceptionStackFrame[]
         */
        function introspectStack( array $trace )
        {
            $stackFrames = array();

            foreach ( $trace as $frame )
            {
                $object = array_key_exists( 'object', $frame )
                        ? $this->reference( $frame[ 'object' ] ) : null;
                $args   = null;

                if ( array_key_exists( 'args', $frame ) )
                {
                    $args = array();

                    foreach ( $frame[ 'args' ] as &$arg )
                        $args[ ] = $this->reference( $arg );
                }

                $type     = ArrayUtil::get( $frame, 'type' );
                $function = ArrayUtil::get( $frame, 'function' );
                $class    = ArrayUtil::get( $frame, 'class' );
                $file     = ArrayUtil::get( $frame, 'file' );
                $line     = ArrayUtil::get( $frame, 'line' );

                $stackFrames[ ] = new Values\ValueExceptionStackFrame( $type, $function, $object, $class,
                                                                       $args, $file, $line );
            }

            return $stackFrames;
        }

        /**
         * @param array|null $locals
         *
         * @return Values\ValuePoolReference[]|null
         */
        function introspectLocalVariables( array $locals = null )
        {
            if ( $locals === null )
                return null;

            $reflected = array();

            foreach ( $locals as $k => &$v )
                $reflected[ $k ] = $this->reference( $v );

            return $reflected;
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
                    $value = $this->reference( $globalValue );

                    $globals[ ] = new Values\ValueExceptionGlobalState( null, null, $name, $value, null );
                }
            }

            foreach ( get_declared_classes() as $class )
            {
                $reflection = new \ReflectionClass( $class );

                foreach ( $reflection->getProperties( \ReflectionProperty::IS_STATIC ) as $property )
                {
                    $property->setAccessible( true );

                    $value  = $this->reference( Ref::create( $property->getValue() ) );
                    $access = self::propertyOrMethodAccess( $property );
                    $class  = $property->class;
                    $name   = $property->name;

                    $globals[ ] = new Values\ValueExceptionGlobalState( $class, null, $name, $value, $access );
                }

                foreach ( $reflection->getMethods() as $method )
                {
                    foreach ( $method->getStaticVariables() as $name => $value )
                    {
                        $value    = $this->reference( $value );
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
                    $reflection = new \ReflectionFunction( $function );

                    foreach ( $reflection->getStaticVariables() as $name => $value )
                    {
                        $value    = $this->reference( $value );
                        $function = $reflection->name;

                        $globals[ ] = new Values\ValueExceptionGlobalState( null, $function, $name, $value, null );
                    }
                }
            }

            return $globals;
        }
    }
}

namespace PrettyPrinter\Values
{
    use PrettyPrinter\PrettyPrinter;
    use PrettyPrinter\Utils\Table;
    use PrettyPrinter\Utils\Text;

    class ValuePool
    {
        /** @var Value[] */
        private $cells = array();
        private $nextId = 0;

        function newReference() { return new ValuePoolReference( $this, $this->nextId++ ); }

        function set( $id, Value $value ) { $this->cells[ $id ] = $value; }

        function get( $id ) { return $this->cells[ $id ]; }

        function isEmpty( $id ) { return !array_key_exists( $id, $this->cells ); }

        function clear( $id ) { unset( $this->cells[ $id ] ); }
    }

    class ValuePoolReference
    {
        private $memory, $id;

        function __construct( ValuePool $memory, $id )
        {
            $this->memory = $memory;
            $this->id     = $id;
        }

        function get() { return $this->memory->get( $this->id ); }

        function isEmpty() { return $this->memory->isEmpty( $this->id ); }

        function set( Value $value ) { $this->memory->set( $this->id, $value ); }

        function clear() { $this->memory->clear( $this->id ); }

        function render( PrettyPrinter $settings ) { return $this->get()->render( $settings ); }
    }

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
                return new Text( 'array()' );

            $table      = new Table;

            foreach ( $this->keyValuePairs as $keyValuePair )
            {
                if ( ( $table->count() + 1 ) > $settings->getMaxArrayEntries() )
                    break;

                $key   = $keyValuePair->key()->render( $settings );
                $value = $keyValuePair->value()->render( $settings );

                if ( $table->count() != count( $this->keyValuePairs ) - 1 )
                    $value->append( ',' );

                $table->addRow( $this->isAssociative ? array( $key, $value->prepend( ' => ' ) ) : array( $value ) );
            }

            $result = $table->render();

            if ( $table->count() < count( $this->keyValuePairs ) )
                $result->addLine( '...' );

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

        private function renderGlobals( PrettyPrinter $settings )
        {
            $table = new Table;

            foreach ( $this->globals as $global )
                $table->addRow( array( $global->renderVar( $settings ),
                                       $global->renderValue( $settings )->wrap( ' = ', ';' ) ) );

            return $table->count() > 0 ? $table->render() : new Text( 'none' );
        }

        private function renderLocals( PrettyPrinter $settings )
        {
            $table = new Table;

            foreach ( $this->locals as $name => $value )
                $table->addRow( array( ValueString::renderVariable( $settings, $name ),
                                       $value->render( $settings )->wrap( ' = ', ';' ) ) );

            return $table->count() > 0 ? $table->render() : new Text( 'none' );
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

    class ValueExceptionStackFrame
    {
        private $type, $function, $object, $class, $args, $file, $line;

        /**
         * @param string|null               $type
         * @param string|null               $function
         * @param ValuePoolReference|null   $object
         * @param string|null               $class
         * @param ValuePoolReference[]|null $args
         * @param string|null               $file
         * @param int|null                  $line
         */
        function __construct( $type, $function, $object, $class, $args, $file, $line )
        {
            $this->type     = $type;
            $this->function = $function;
            $this->object   = $object;
            $this->class    = $class;
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
                return new Text( "$this->class" );

            return $this->object->render( $settings );
        }

        private function renderArgs( PrettyPrinter $settings )
        {
            if ( $this->args === null )
                return new Text( '( ? )' );

            if ( $this->args === array() )
                return new Text( '()' );

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
                    $result->append( ', ' );

                if ( $isMultiLine )
                    $result->addLines( $pretty );
                else
                    $result->appendLines( $pretty );
            }

            return $result->wrap( '( ', ' )' );
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

        function renderVar( PrettyPrinter $settings )
        {
            return $this->prefix()->appendLines( ValueString::renderVariable( $settings, $this->name ) );
        }

        function renderValue( PrettyPrinter $settings )
        {
            return $this->value->render( $settings );
        }

        private function prefix()
        {
            if ( $this->class !== null && $this->function !== null )
                return new Text( "function $this->class::$this->function()::static " );

            if ( $this->class !== null )
                return new Text( "$this->access static $this->class::" );

            if ( $this->function !== null )
                return new Text( "function $this->function()::static " );

            $superGlobals = array( '_POST', '_GET', '_SESSION', '_COOKIE', '_FILES', '_REQUEST', '_ENV', '_SERVER' );

            return new Text( in_array( $this->name, $superGlobals, true ) ? '' : 'global ' );
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
         * @param string                $class
         * @param ValueObjectProperty[] $properties
         */
        function __construct( $class, array $properties )
        {
            $this->class      = $class;
            $this->properties = $properties;
        }

        function render( PrettyPrinter $settings )
        {
            $table = new Table;

            foreach ( $this->properties as $property )
            {
                if ( ( $table->count() + 1) > $settings->getMaxObjectProperties() )
                    break;

                $value  = $property->value();
                $name   = $property->name();
                $access = $property->access();

                $table->addRow( array( ValueString::renderVariable( $settings, $name )->prepend( "$access " ),
                                       $value->render( $settings )->wrap( ' = ', ';' ) ) );
            }

            $result = $table->render();

            if ( $table->count() < count( $this->properties ) )
                $result->addLine( '...' );

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

        function value() { return $this->value; }

        function name() { return $this->name; }

        function access() { return $this->access; }

        function className() { return $this->class; }
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
                return new Text( "$$name" );

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

            return new Text( "\"$escaped" . ( strlen( $string ) >= $length ? '...' : '"' ) );
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

