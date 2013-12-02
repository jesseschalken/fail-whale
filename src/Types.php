<?php

namespace PrettyPrinter\Introspection
{
	use PrettyPrinter\Types;
	use PrettyPrinter\Utils\ArrayUtil;
	use PrettyPrinter\Utils\Ref;

	class Introspection
	{
		private $memory;
		/** @var TypeIntrospection[] */
		private $types;

		function __construct()
		{
			$this->memory = new Types\Memory;
			$this->types  = array( 'boolean'      => new TypeBool( $this ),
			                       'integer'      => new TypeInt( $this ),
			                       'double'       => new TypeFloat( $this ),
			                       'string'       => new TypeString( $this ),
			                       'array'        => new TypeArray( $this ),
			                       'object'       => new TypeObject( $this ),
			                       'resource'     => new TypeResource( $this ),
			                       'NULL'         => new TypeNull( $this ),
			                       'unknown type' => new TypeUnknown( $this ) );
		}

		function newID() { return $this->memory->newID(); }

		function toReference( &$value )
		{
			return $this->types[ gettype( $value ) ]->toReference( $value );
		}
	}

	abstract class TypeIntrospection
	{
		private $introspection;

		function __construct( Introspection $introspection )
		{
			$this->introspection = $introspection;
		}

		function toReference( &$value )
		{
			$id = $this->toID( $value );

			if ( !$id->has() )
				$id->set( $this->reflect( $value ) );

			return $id;
		}

		/**
		 * @param $value
		 *
		 * @return Types\MemoryReference
		 */
		protected abstract function toID( &$value );

		protected function newID() { return $this->introspection->newID(); }

		/**
		 * @param mixed $value
		 *
		 * @return Types\ReflectedValue
		 */
		protected abstract function reflect( $value );

		protected function introspect( &$value ) { return $this->introspection->toReference( $value ); }
	}

	abstract class TypeCaching extends TypeIntrospection
	{
		private $cache = array();

		protected function toString( $value ) { return "$value"; }

		protected function toID( &$value )
		{
			$string = $this->toString( $value );

			if ( isset( $this->cache[ $string ] ) )
				return $this->cache[ $string ];

			$id = $this->newID();

			$this->cache[ $string ] = $id;

			return $id;
		}
	}

	class TypeArray extends TypeIntrospection
	{
		private $references = array();

		protected function toID( &$value )
		{
			foreach ( $this->references as $array )
				if ( Ref::equal( $value, $array[ 'ref' ] ) )
					return $array[ 'id' ];

			$id = $this->newID();

			$this->references[ ] = array( 'id' => $id, 'ref' => &$value );

			return $id;
		}

		protected function reflect( $value )
		{
			$keyValuePairs = array();

			foreach ( $value as $k => &$v )
			{
				$keyValuePairs[ ] =
						new Types\ReflectedArrayKeyValuePair( $this->introspect( $k ), $this->introspect( $v ) );
			}

			return new Types\ReflectedArray( ArrayUtil::isAssoc( $value ), $keyValuePairs );
		}
	}

	class TypeBool extends TypeCaching
	{
		protected function reflect( $value ) { return new Types\ReflectedBool( $value ); }
	}

	class TypeString extends TypeCaching
	{
		protected function reflect( $value ) { return new Types\ReflectedString( $value ); }
	}

	class TypeInt extends TypeCaching
	{
		protected function reflect( $value ) { return new Types\ReflectedInt( $value ); }
	}

	class TypeObject extends TypeCaching
	{
		protected function toString( $value ) { return spl_object_hash( $value ); }

		protected function reflect( $object )
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

					$access        = Types\ReflectedException::propertyOrMethodAccess( $property );
					$value         = $this->introspect( Ref::create( $property->getValue( $object ) ) );
					$properties[ ] = new Types\ReflectedObjectProperty( $value, $property->name,
					                                                    $access, $property->class );
				}
			}

			return new Types\ReflectedObject( get_class( $object ), $properties );
		}
	}

	class TypeFloat extends TypeCaching
	{
		protected function reflect( $value ) { return new Types\ReflectedFloat( $value ); }
	}

	class TypeResource extends TypeCaching
	{
		protected function reflect( $value ) { return new Types\ReflectedResource( get_resource_type( $value ) ); }
	}

	class TypeNull extends TypeCaching
	{
		protected function reflect( $value ) { return new Types\ReflectedNull; }
	}

	class TypeUnknown extends TypeCaching
	{
		protected function toString( $value ) { return ''; }

		protected function reflect( $value ) { return new Types\ReflectedUnknown; }
	}
}

namespace PrettyPrinter\Types
{
	use PrettyPrinter\HasFullTrace;
	use PrettyPrinter\HasLocalVariables;
	use PrettyPrinter\Introspection\Introspection;
	use PrettyPrinter\PrettyPrinter;
	use PrettyPrinter\Utils\ArrayUtil;
	use PrettyPrinter\Utils\Ref;
	use PrettyPrinter\Utils\Table;
	use PrettyPrinter\Utils\Text;

	class Memory
	{
		/** @var ReflectedValue[] */
		private $cells = array();
		private $nextId = 0;

		function newID() { return new MemoryReference( $this, $this->nextId++ ); }

		function set( $id, ReflectedValue $value )
		{
			$this->cells[ $id ] = $value;
		}

		function get( $id ) { return $this->cells[ $id ]; }

		function has( $id ) { return array_key_exists( $id, $this->cells ); }

		function reference( $id ) { return new MemoryReference( $this, $id ); }
	}

	class MemoryReference
	{
		private $memory, $id;

		function __construct( Memory $memory, $id )
		{
			$this->memory = $memory;
			$this->id     = $id;
		}

		function get() { return $this->memory->get( $this->id ); }

		function has() { return $this->memory->has( $this->id ); }

		function set( ReflectedValue $value ) { $this->memory->set( $this->id, $value ); }

		function render( PrettyPrinter $settings ) { return $this->get()->render( $settings ); }
	}

	abstract class ReflectedValue
	{
		/**
		 * @param PrettyPrinter $settings
		 *
		 * @return Text
		 */
		abstract function render( PrettyPrinter $settings );
	}

	class ReflectedArray extends ReflectedValue
	{
		private $isAssociative, $keyValuePairs;

		/**
		 * @param bool                         $isAssociative
		 * @param ReflectedArrayKeyValuePair[] $keyValuePairs
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

			$maxEntries = $settings->maxArrayEntries()->get();
			$table      = new Table;

			foreach ( $this->keyValuePairs as $keyValuePair )
			{
				if ( $table->count() >= $maxEntries )
					break;

				$key   = $keyValuePair->key()->render( $settings );
				$value = $keyValuePair->value()->render( $settings );

				if ( $table->count() != count( $this->keyValuePairs ) - 1 )
					$value->append( ',' );

				$table->addRow( $this->isAssociative ? array( $key, $value->prepend( ' => ' ) ) : array( $value ) );
			}

			$result = $table->render();

			if ( $table->count() != count( $this->keyValuePairs ) )
				$result->addLine( '...' );

			$result->wrap( 'array( ', ' )' );

			return $result;
		}
	}

	final class ReflectedArrayKeyValuePair
	{
		private $key, $value;

		function __construct( MemoryReference $key, MemoryReference $value )
		{
			$this->key   = $key;
			$this->value = $value;
		}

		function key() { return $this->key; }

		function value() { return $this->value; }
	}

	class ReflectedBool extends ReflectedValue
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

	class ReflectedException extends ReflectedValue
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

		static function reflect( Introspection $memory, \Exception $exception )
		{
			$localVariables = $exception instanceof HasLocalVariables ? $exception->getLocalVariables() : null;
			$trace          = $exception instanceof HasFullTrace ? $exception->getFullTrace() : $exception->getTrace();

			$locals  = self::reflectLocalVariables( $memory, $localVariables );
			$globals = self::reflectGlobalVariables( $memory );
			$stack   = self::reflectStack( $memory, $trace );

			$previous = $exception->getPrevious();
			$previous = $previous === null ? null : self::reflect( $memory, $previous );

			$class   = get_class( $exception );
			$file    = $exception->getFile();
			$line    = $exception->getLine();
			$code    = $exception->getCode();
			$message = $exception->getMessage();

			return new self( $class, $file, $line, $stack, $globals, $locals, $code, $message, $previous );
		}

		protected static function reflectStack( Introspection $memory, array $trace )
		{
			$stackFrames = array();

			foreach ( $trace as $frame )
				$stackFrames[ ] = ReflectedExceptionStackFrame::reflect( $memory, $frame );

			return $stackFrames;
		}

		/**
		 * @param Introspection $memory
		 * @param array|null    $locals
		 *
		 * @return MemoryReference[]|null
		 */
		protected static function reflectLocalVariables( Introspection $memory, array $locals = null )
		{
			if ( $locals === null )
				return null;

			$reflected = array();

			foreach ( $locals as $k => &$v )
				$reflected[ $k ] = $memory->toReference( $v );

			return $reflected;
		}

		private static function reflectGlobalVariables( Introspection $memory )
		{
			$globals = array();

			foreach ( $GLOBALS as $name => &$globalValue )
			{
				if ( $name !== 'GLOBALS' )
				{
					$value = $memory->toReference( $globalValue );

					$globals[ ] = new ReflectedGlobal( null, null, $name, $value, null );
				}
			}

			foreach ( get_declared_classes() as $class )
			{
				$reflection = new \ReflectionClass( $class );

				foreach ( $reflection->getProperties( \ReflectionProperty::IS_STATIC ) as $property )
				{
					$property->setAccessible( true );

					$value  = $memory->toReference( Ref::create( $property->getValue() ) );
					$access = self::propertyOrMethodAccess( $property );
					$class  = $property->class;
					$name   = $property->name;

					$globals[ ] = new ReflectedGlobal( $class, null, $name, $value, $access );
				}

				foreach ( $reflection->getMethods() as $method )
				{
					foreach ( $method->getStaticVariables() as $name => $value )
					{
						$value    = $memory->toReference( $value );
						$class    = $method->class;
						$function = $method->getName();

						$globals[ ] = new ReflectedGlobal( $class, $function, $name, $value, null );
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
						$value    = $memory->toReference( $value );
						$function = $reflection->name;

						$globals[ ] = new ReflectedGlobal( null, $function, $name, $value, null );
					}
				}
			}

			return $globals;
		}

		private $class, $file, $line, $stack, $globals, $locals, $code, $message, $previous;

		/**
		 * @param string                         $class
		 * @param string                         $file
		 * @param int                            $line
		 * @param ReflectedExceptionStackFrame[] $stack
		 * @param ReflectedGlobal[]              $globals
		 * @param MemoryReference[]|null         $locals
		 * @param mixed                          $code
		 * @param string                         $message
		 * @param ReflectedException|null        $previous
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

			if ( $settings->showExceptionGlobalVariables()->get() )
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
				$table->addRow( array( ReflectedString::renderVariable( $settings, $name ),
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

			if ( $this->locals !== null && $settings->showExceptionLocalVariables()->get() )
			{
				$text->addLine( "local variables:" );
				$text->addLines( $this->renderLocals( $settings )->indent() );
				$text->addLine();
			}

			if ( $settings->showExceptionStackTrace()->get() )
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

	final class ReflectedExceptionStackFrame
	{
		private $type, $function, $object, $class, $args, $file, $line;

		static function reflect( Introspection $memory, array $stackFrame )
		{
			$object =
					array_key_exists( 'object', $stackFrame ) ? $memory->toReference( $stackFrame[ 'object' ] ) : null;
			$args   = null;

			if ( array_key_exists( 'args', $stackFrame ) )
			{
				$args = array();

				foreach ( $stackFrame[ 'args' ] as &$arg )
					$args[ ] = $memory->toReference( $arg );
			}

			$type     = ArrayUtil::get( $stackFrame, 'type' );
			$function = ArrayUtil::get( $stackFrame, 'function' );
			$class    = ArrayUtil::get( $stackFrame, 'class' );
			$file     = ArrayUtil::get( $stackFrame, 'file' );
			$line     = ArrayUtil::get( $stackFrame, 'line' );

			return new self( $type, $function, $object, $class, $args, $file, $line );
		}

		/**
		 * @param string|null            $type
		 * @param string|null            $function
		 * @param MemoryReference|null   $object
		 * @param string|null            $class
		 * @param MemoryReference[]|null $args
		 * @param string|null            $file
		 * @param int|null               $line
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

	final class ReflectedGlobal
	{
		static function reflect( Introspection $memory, $class, $function, $name, &$value, $access )
		{
			return new self( $class, $function, $name, $memory->toReference( $value ), $access );
		}

		private $class, $function, $name, $value, $access;

		/**
		 * @param string|null     $class
		 * @param string|null     $function
		 * @param string          $name
		 * @param MemoryReference $value
		 * @param string|null     $access
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
			return $this->prefix()->appendLines( ReflectedString::renderVariable( $settings, $this->name ) );
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

	class ReflectedFloat extends ReflectedValue
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

	class ReflectedInt extends ReflectedValue
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

	class ReflectedNull extends ReflectedValue
	{
		function render( PrettyPrinter $settings ) { return new Text( 'null' ); }
	}

	class ReflectedObject extends ReflectedValue
	{
		private $class, $properties;

		/**
		 * @param string                    $class
		 * @param ReflectedObjectProperty[] $properties
		 */
		function __construct( $class, array $properties )
		{
			$this->class      = $class;
			$this->properties = $properties;
		}

		function render( PrettyPrinter $settings )
		{
			$maxProperties = $settings->maxObjectProperties()->get();
			$numProperties = 0;
			$table         = new Table;

			foreach ( $this->properties as $property )
			{
				$numProperties++;

				if ( $table->count() >= $maxProperties )
					continue;

				$value  = $property->value();
				$name   = $property->name();
				$access = $property->access();

				$table->addRow( array( ReflectedString::renderVariable( $settings, $name )->prepend( "$access " ),
				                       $value->render( $settings )->wrap( ' = ', ';' ) ) );
			}

			$result = $table->render();

			if ( $table->count() != $numProperties )
				$result->addLine( '...' );

			return $result->indent( 2 )->wrapLines( "new $this->class {", "}" );
		}
	}

	final class ReflectedObjectProperty
	{
		private $value, $name, $access, $class;

		/**
		 * @param MemoryReference $value
		 * @param string          $name
		 * @param string          $access
		 * @param string          $class
		 */
		function __construct( MemoryReference $value, $name, $access, $class )
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

	class ReflectedResource extends ReflectedValue
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

	class ReflectedString extends ReflectedValue
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
			$escapeTabs    = $settings->escapeTabsInStrings()->get();
			$splitNewlines = $settings->splitMultiLineStrings()->get();

			$characterEscapeCache = array( "\\" => '\\\\',
			                               "\$" => '\$',
			                               "\r" => '\r',
			                               "\v" => '\v',
			                               "\f" => '\f',
			                               "\"" => '\"',
			                               "\t" => $escapeTabs ? '\t' : "\t",
			                               "\n" => $splitNewlines ? "\\n\" .\n\"" : '\n' );

			$escaped = '';
			$length  = min( strlen( $string ), $settings->maxStringLength()->get() );

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

			return new Text( "\"$escaped" . ( $length == strlen( $string ) ? '"' : "..." ) );
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

	class ReflectedUnknown extends ReflectedValue
	{
		function render( PrettyPrinter $settings ) { return new Text( 'unknown type' ); }
	}
}

