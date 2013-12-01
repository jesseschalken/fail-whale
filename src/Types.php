<?php

namespace PrettyPrinter
{
	use PrettyPrinter\Types;

	class Memory
	{
		/** @var Types\Value[] */
		private $values = array();
		/** @var int[][] */
		private $cache = array();
		/** @var int */
		private $nextId = 0;

		function prettyPrintRef( &$value, PrettyPrinter $settings )
		{
			return $this->toID( $value )->render( $settings );
		}

		function toID( &$value )
		{
			$value  = $this->createValue( $value );
			$type   = $value->type();
			$string = $value->toString();

			if ( isset( $this->cache[ $type ][ $string ] ) )
				return $this->cache[ $type ][ $string ];

			$id = $this->nextId++;

			$this->values[ $id ]             = $value;
			$this->cache[ $type ][ $string ] = $id;

			return new MemoryReference( $this, $id );
		}

		function fromID( $id )
		{
			return $this->values[ $id ];
		}

		/**
		 * @param mixed $value
		 *
		 * @return Types\Value
		 */
		function createValue( &$value )
		{
			if ( is_bool( $value ) )
				return new Types\Boolean( $this, $value );

			if ( is_string( $value ) )
				return new Types\String( $this, $value );

			if ( is_int( $value ) )
				return new Types\Integer( $this, $value );

			if ( is_float( $value ) )
				return new Types\Float( $this, $value );

			if ( is_object( $value ) )
				return new Types\Object( $this, $value );

			if ( is_array( $value ) )
				return new Types\Array1( $this, $value );

			if ( is_resource( $value ) )
				return new Types\Resource( $this, $value );

			if ( is_null( $value ) )
				return new Types\Null( $this );

			return new Types\Unknown( $this );
		}

		/**
		 * @param PrettyPrinter $settings
		 * @param string        $name
		 *
		 * @return Utils\Text
		 */
		static function prettyPrintVariable( PrettyPrinter $settings, $name )
		{
			if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name ) )
				return new Utils\Text( "$$name" );

			return Types\String::renderString( $settings, $name )->wrap( '${', '}' );
		}
	}

	class MemoryReference
	{
		private $memory, $id;

		/**
		 * @param Memory $memory
		 * @param int    $id
		 */
		function __construct( Memory $memory, $id )
		{
			$this->memory = $memory;
			$this->id     = $id;
		}

		function render( PrettyPrinter $settings )
		{
			return $this->memory->fromID( $this->id )->render( $settings );
		}
	}
}

namespace PrettyPrinter\Types
{
	use PrettyPrinter\HasFullTrace;
	use PrettyPrinter\HasLocalVariables;
	use PrettyPrinter\Memory;
	use PrettyPrinter\MemoryReference;
	use PrettyPrinter\PrettyPrinter;
	use PrettyPrinter\Utils\ArrayUtil;
	use PrettyPrinter\Utils\Ref;
	use PrettyPrinter\Utils\Table;
	use PrettyPrinter\Utils\Text;

	abstract class Value
	{
		private $memory;

		function __construct( Memory $memory )
		{
			$this->memory = $memory;
		}

		/**
		 * @param PrettyPrinter $settings
		 *
		 * @return Text
		 */
		abstract function render( PrettyPrinter $settings );

		/** @return string */
		abstract function type();

		/** @return string */
		abstract function toString();

		protected static function prettyPrintVariable( $varName, PrettyPrinter $settings )
		{
			return Memory::prettyPrintVariable( $settings, $varName );
		}

		function memory() { return $this->memory; }

		protected function toID( &$value )
		{
			return $this->memory->toID( $value );
		}
	}

	/**
	 * Called "Array1" because "Array" is a reserved word.
	 */
	final class Array1 extends Value
	{
		private static $uniqueStringId = 0;
		private $array;

		function __construct( array &$array )
		{
			return $this->array =& $array;
		}

		function render( PrettyPrinter $settings )
		{
			$keyValuePairs = array();

			foreach ( $this->array as $k => &$v )
			{
				$keyValuePairs[ ] = new KeyValuePair( $this->toID( $k ), $this->toID( $v ) );
			}

			return $this->render2( ArrayUtil::isAssoc( $this->array ), $keyValuePairs, $settings );
		}

		/**
		 * @param bool                         $isAssociative
		 * @param KeyValuePair[]               $keyValuePairs
		 *
		 * @param \PrettyPrinter\PrettyPrinter $settings
		 *
		 * @return Text
		 */
		function render2( $isAssociative, array $keyValuePairs, PrettyPrinter $settings )
		{
			if ( $keyValuePairs === array() )
				return new Text( 'array()' );

			$maxEntries = $settings->maxArrayEntries()->get();
			$table      = new Table;

			foreach ( $keyValuePairs as $keyValuePair )
			{
				if ( $table->count() >= $maxEntries )
					break;

				$key   = $keyValuePair->key()->render( $settings );
				$value = $keyValuePair->value()->render( $settings );

				if ( $table->count() != count( $keyValuePairs ) - 1 )
					$value->append( ',' );

				$table->addRow( $isAssociative ? array( $key, $value->prepend( ' => ' ) ) : array( $value ) );
			}

			$result = $table->render();

			if ( $table->count() != count( $keyValuePairs ) )
				$result->addLine( '...' );

			$result->wrap( 'array( ', ' )' );

			return $result;
		}

		function type() { return 'array'; }

		function toString() { return (string) self::$uniqueStringId++; }
	}

	final class KeyValuePair
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

	final class Boolean extends Value
	{
		private $bool;

		/**
		 * @param \PrettyPrinter\Memory $memory
		 * @param bool                  $value
		 */
		function __construct( Memory $memory, $value )
		{
			$this->bool = $value;

			parent::__construct( $memory );
		}

		function render( PrettyPrinter $settings ) { return new Text( $this->bool ? 'true' : 'false' ); }

		function type() { return 'boolean'; }

		function toString() { return $this->bool ? '1' : '0'; }
	}

	final class Exception extends Value
	{
		private $exception;

		function __construct( Memory $memory, \Exception $exception )
		{
			$this->exception = $exception;

			parent::__construct( $memory );
		}

		/**
		 * @param \ReflectionProperty|\ReflectionMethod $property
		 *
		 * @return string
		 */
		static function propertyOrMethodAccess( $property )
		{
			return $property->isPrivate() ? 'private' : ( $property->isPublic() ? 'public' : 'protected' );
		}

		function type() { return 'exception'; }

		function toString() { return spl_object_hash( $this->exception ); }

		function render( PrettyPrinter $settings )
		{
			return ReflectedException::reflect( $this->memory(), $this->exception )->render( $settings );
		}
	}

	class ReflectedException
	{
		static function reflect( Memory $memory, \Exception $exception )
		{
			$localVariables = $exception instanceof HasLocalVariables ? $exception->getLocalVariables() : null;
			$trace          = $exception instanceof HasFullTrace ? $exception->getFullTrace() : $exception->getTrace();

			$locals         = self::reflectLocalVariables( $memory, $localVariables );
			$globals        = self::reflectGlobalVariables( $memory );
			$stack          = self::reflectStack( $memory, $trace );

			$previous = $exception->getPrevious();
			$previous = $previous === null ? null : self::reflect( $memory, $previous );

			$class   = get_class( $exception );
			$file    = $exception->getFile();
			$line    = $exception->getLine();
			$code    = $exception->getCode();
			$message = $exception->getMessage();

			return new self( $class, $file, $line, $stack, $globals, $locals, $code, $message, $previous );
		}
		
		protected static function reflectStack( Memory $memory, array $trace )
		{
			$stackFrames = array();

			foreach ( $trace as $frame )
				$stackFrames[ ] = StackFrame::reflect( $memory, $frame );
			
			return $stackFrames;
		}

		/**
		 * @param Memory     $memory
		 * @param array|null $locals
		 *
		 * @return MemoryReference[]|null
		 */
		protected static function reflectLocalVariables( Memory $memory, array $locals = null )
		{
			if ( $locals === null )
				return null;
			
			$reflected = array();
			
			foreach ( $locals as $k => &$v )
				$reflected[ $k ] = $memory->toID( $v );
			
			return $reflected;
		}
		
		private static function reflectGlobalVariables( Memory $memory )
		{
			$globals = array();

			foreach ( $GLOBALS as $name => &$globalValue )
			{
				if ( $name !== 'GLOBALS' )
				{
					$value = $memory->toID( $globalValue );

					$globals[ ] = new ReflectedGlobal( null, null, $name, $value, null );
				}
			}

			foreach ( get_declared_classes() as $class )
			{
				$reflection = new \ReflectionClass( $class );

				foreach ( $reflection->getProperties( \ReflectionProperty::IS_STATIC ) as $property )
				{
					$property->setAccessible( true );

					$value  = $memory->toID( Ref::create( $property->getValue() ) );
					$access = Exception::propertyOrMethodAccess( $property );
					$class  = $property->class;
					$name   = $property->name;

					$globals[ ] = new ReflectedGlobal( $class, null, $name, $value, $access );
				}

				foreach ( $reflection->getMethods() as $method )
				{
					foreach ( $method->getStaticVariables() as $name => $value )
					{
						$value    = $memory->toID( $value );
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
						$value    = $memory->toID( $value );
						$function = $reflection->name;

						$globals[ ] = new ReflectedGlobal( null, $function, $name, $value, null );
					}
				}
			}

			return $globals;
		}
		
		private $class, $file, $line, $stack, $globals, $locals, $code, $message, $previous;

		/**
		 * @param string                  $class
		 * @param string                  $file
		 * @param int                     $line
		 * @param StackFrame[]            $stack
		 * @param ReflectedGlobal[]       $globals
		 * @param MemoryReference[]|null  $locals
		 * @param mixed                   $code
		 * @param string                  $message
		 * @param ReflectedException|null $previous
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
				$table->addRow( array( Memory::prettyPrintVariable( $settings, $name ),
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
				$i++;
			}

			$text->addLine( "#$i {main}" );

			return $text;
		}
	}

	final class StackFrame
	{
		private $type, $function, $object, $class, $args, $file, $line;

		static function reflect( Memory $memory, array $stackFrame )
		{
			$object = array_key_exists( 'object', $stackFrame ) ? $memory->toID( $stackFrame[ 'object' ] ) : null;
			$args   = null;

			if ( array_key_exists( 'args', $stackFrame ) )
			{
				$args = array();

				foreach ( $stackFrame[ 'args' ] as &$arg )
					$args[ ] = $memory->toID( $arg );
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
		static function reflect( Memory $memory, $class, $function, $name, &$value, $access )
		{
			return new self( $class, $function, $name, $memory->toID( $value ), $access );
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
			return $this->prefix()->appendLines( Memory::prettyPrintVariable( $settings, $this->name ) );
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

	final class Float extends Value
	{
		/**
		 * @var float
		 */
		private $float;

		function render( PrettyPrinter $settings )
		{
			$float = $this->float;
			$int   = (int) $float;

			return new Text( "$int" === "$float" ? "$float.0" : "$float" );
		}

		/**
		 * @param Memory $memory
		 * @param float  $float
		 */
		function __construct( Memory $memory, $float )
		{
			parent::__construct( $memory );

			$this->float = $float;
		}

		function type() { return 'float'; }

		function toString() { return "$this->float"; }
	}

	final class Integer extends Value
	{
		/**
		 * @var int
		 */
		private $int;

		function render( PrettyPrinter $settings ) { return new Text( "$this->int" ); }

		/**
		 * @param Memory $memory
		 * @param int    $int
		 */
		function __construct( Memory $memory, $int )
		{
			parent::__construct( $memory );
			$this->int = $int;
		}

		function type() { return 'integer'; }

		function toString() { return "$this->int"; }
	}

	final class Null extends Value
	{
		function render( PrettyPrinter $settings ) { return new Text( 'null' ); }

		function type() { return 'null'; }

		function toString() { return ''; }
	}

	final class Object extends Value
	{
		/**
		 * @var object
		 */
		private $object;

		function render( PrettyPrinter $settings )
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

					$properties[ ] = new ObjectProperty( $this->toID( $property->getValue( $this->object ) ),
					                                     $property->name,
					                                     Exception::propertyOrMethodAccess( $property ),
					                                     $property->class );
				}
			}

			return $this->render2( get_class( $this->object ), $properties, $settings );
		}

		/**
		 * @param string                       $class
		 * @param ObjectProperty[]             $properties
		 *
		 * @param \PrettyPrinter\PrettyPrinter $settings
		 *
		 * @return Text
		 */
		function render2( $class, array $properties, PrettyPrinter $settings )
		{
			$maxProperties = $settings->maxObjectProperties()->get();
			$numProperties = 0;
			$table         = new Table;

			foreach ( $properties as $property )
			{
				$numProperties++;

				if ( $table->count() >= $maxProperties )
					continue;

				$value  = $property->value();
				$name   = $property->name();
				$access = $property->access();

				$table->addRow( array( self::prettyPrintVariable( $name, $settings )->prepend( "$access " ),
				                       $value->render( $settings )->wrap( ' = ', ';' ) ) );
			}

			$result = $table->render();

			if ( $table->count() != $numProperties )
				$result->addLine( '...' );

			return $result->indent( 2 )->wrapLines( "new $class {", "}" );
		}

		/**
		 * @param Memory $memory
		 * @param object $object
		 */
		function __construct( Memory $memory, $object )
		{
			parent::__construct( $memory );
			$this->object = $object;
		}

		function type() { return 'object'; }

		function toString() { return spl_object_hash( $this->object ); }
	}

	final class ObjectProperty
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

	final class Resource extends Value
	{
		private $resource;

		function render( PrettyPrinter $settings )
		{
			return new Text( get_resource_type( $this->resource ) );
		}

		/**
		 * @param Memory   $memory
		 * @param resource $resource
		 */
		function __construct( Memory $memory, $resource )
		{
			parent::__construct( $memory );

			$this->resource = $resource;
		}

		function type() { return 'resource'; }

		function toString() { return "$this->resource"; }
	}

	final class String extends Value
	{
		private $string;

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

		function render( PrettyPrinter $settings )
		{
			return self::renderString( $settings, $this->string );
		}

		/**
		 * @param Memory $memory
		 * @param string $string
		 */
		function __construct( Memory $memory, $string )
		{
			$this->string = $string;

			parent::__construct( $memory );
		}

		function type() { return 'string'; }

		function toString() { return $this->string; }
	}

	final class Unknown extends Value
	{
		function render( PrettyPrinter $settings ) { return new Text( 'unknown type' ); }

		function type() { return 'unknown'; }

		function toString() { return ''; }
	}
}

