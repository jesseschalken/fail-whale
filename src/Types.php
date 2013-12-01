<?php

namespace PrettyPrinter
{
	use PrettyPrinter\Types;

	class Memory
	{
		/** @var PrettyPrinter */
		private $settings;
		/** @var Types\Value[] */
		private $values = array();
		/** @var int[][] */
		private $cache = array();
		/** @var int */
		private $nextId = 0;

		function settings() { return $this->settings; }

		function __construct( PrettyPrinter $settings )
		{
			$this->settings = $settings;
		}

		function prettyPrintRef( &$value )
		{
			return $this->fromID( $this->toID( $value ) )->render();
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

			return $id;
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

		function prettyPrint( $value ) { return $this->prettyPrintRef( $value ); }

		/**
		 * @param string $name
		 *
		 * @return Utils\Text
		 */
		function prettyPrintVariable( $name )
		{
			if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name ) )
				return new Utils\Text( "$$name" );

			$string = new Types\String( $this, $name );

			return $string->render()->wrap( '${', '}' );
		}
	}
}

namespace PrettyPrinter\Types
{
	use PrettyPrinter\ExceptionInfo;
	use PrettyPrinter\Memory;
	use PrettyPrinter\Reflection\Variable;
	use PrettyPrinter\Utils\ArrayUtil;
	use PrettyPrinter\Utils\Table;
	use PrettyPrinter\Utils\Text;

	abstract class Value
	{
		private $memory;

		function __construct( Memory $memory )
		{
			$this->memory = $memory;
		}

		/** @return Text */
		abstract function render();

		/** @return string */
		abstract function type();

		/** @return string */
		abstract function toString();

		protected function settings() { return $this->memory->settings(); }

		protected final function prettyPrintRef( &$value )
		{
			return $this->memory->prettyPrintRef( $value );
		}

		protected final function prettyPrint( $value )
		{
			return $this->memory->prettyPrintRef( $value );
		}

		protected final function prettyPrintVariable( $varName )
		{
			return $this->memory->prettyPrintVariable( $varName );
		}

		protected function memory() { return $this->memory; }

		protected function renderFromID( $value )
		{
			return $this->memory()->fromID( $value )->render();
		}
		
		protected function toID( &$value )
		{
			return $this->memory()->toID( $value );
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

		function render()
		{
			$keyValuePairs = array();

			foreach ( $this->array as $k => &$v )
			{
				$keyValuePairs[ ] = array(
					'key'   => $this->toID( $k ),
					'value' => $this->toID( $v ),
				);
			}

			return $this->render2( ArrayUtil::isAssoc( $this->array ), $keyValuePairs );
		}

		function render2( $isAssociative, array $keyValuePairs )
		{
			if ( empty( $keyValuePairs ) )
				return new Text( 'array()' );

			$maxEntries    = $this->settings()->maxArrayEntries()->get();
			$table         = new Table;

			foreach ( $keyValuePairs as $keyValuePair )
			{
				if ( $table->count() >= $maxEntries )
					break;

				$key   = $keyValuePair[ 'key' ];
				$value = $keyValuePair[ 'value' ];

				$value = $this->renderFromID( $value );

				if ( $table->count() != count( $keyValuePairs ) - 1 )
					$value->append( ',' );

				$table->addRow( $isAssociative
						                ? array( $this->renderFromID( $key ), $value->prepend( ' => ' ) )
						                : array( $value ) );
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

		function render() { return new Text( $this->bool ? 'true' : 'false' ); }

		function type() { return 'boolean'; }

		function toString() { return $this->bool ? '1' : '0'; }
	}

	final class Exception extends Value
	{
		private $exception;

		function __construct( Memory $memory, ExceptionInfo $exception )
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

		function render()
		{
			$reflected = ReflectedException::reflect( $this->memory(), $this->exception );
			return $reflected->render();
		}
	}

	final class ReflectedException
	{
		static function reflect( Memory $memory, ExceptionInfo $exception )
		{
			$stackFrames = array();
			$locals      = array();
			$globals     = array();

			foreach ( $exception->stackTrace() as $frame )
				$stackFrames[ ] = StackFrame::reflect( $memory, $frame );

			foreach ( $exception->localVariables() as $name => $value )
				$locals[ $name ] = $memory->toID( $value );
			
			foreach ( $exception->globalVariables() as $global )
				$globals[] = ReflectedGlobal::reflect( $memory, $global );

			$previous = $exception->previous();
			$previous = $previous === null ? null : self::reflect( $memory, $previous );

			$class   = $exception->exceptionClassName();
			$file    = $exception->file();
			$line    = $exception->line();
			$code    = $exception->code();
			$message = $exception->message();

			return new self( $memory, $class, $file, $line, $stackFrames,
			                 $globals, $locals, $code, $message, $previous );
		}

		private $class, $file, $line, $stack, $globals, $locals, $code, $message;
		/**
		 * @var \PrettyPrinter\Memory
		 */
		private $memory;
		/**
		 * @var null|ReflectedException
		 */
		private $previous;

		/**
		 * @param \PrettyPrinter\Memory   $memory
		 * @param string                  $class
		 * @param string                  $file
		 * @param int                     $line
		 * @param StackFrame[]            $stack
		 * @param ReflectedGlobal[]       $globals
		 * @param int[]|null              $locals
		 * @param mixed                   $code
		 * @param string                  $message
		 * @param ReflectedException|null $previous
		 */
		function __construct( Memory $memory, $class, $file, $line, array $stack,
		                      array $globals, array $locals, $code, $message, self $previous = null )
		{
			$this->class    = $class;
			$this->file     = $file;
			$this->line     = $line;
			$this->stack    = $stack;
			$this->globals  = $globals;
			$this->locals   = $locals;
			$this->code     = $code;
			$this->message  = $message;
			$this->memory   = $memory;
			$this->previous = $previous;
		}

		/**
		 * @return Text
		 */
		function render()
		{
			return $this->prettyPrintExceptionWithoutGlobals()
			            ->addLines( $this->prettyPrintGlobalState() );
		}

		private function prettyPrintGlobalState()
		{
			if ( !$this->settings()->showExceptionGlobalVariables()->get() )
				return new Text;

			return $this->prettyPrintGlobalVariables()->indent()->wrapLines( 'global variables:' );
		}

		private function prettyPrintGlobalVariables()
		{
			if ( empty( $this->globals ) )
				return new Text( 'none' );

			$table = new Table;

			foreach ( $this->globals as $global )
				$table->addRow( array( $global->prettyPrint( $this->memory ),
				                       $this->memory->fromID( $global->value() )->render()->wrap( ' = ', ';' ) ) );

			return $table->render();
		}

		private function prettyPrintLocalVariables()
		{
			if ( !$this->settings()->showExceptionLocalVariables()->get() )
				return new Text;

			if ( $this->locals === null )
				return new Text;

			$table = new Table;

			foreach ( $this->locals as $name => $value )
				$table->addRow( array( $this->memory->prettyPrintVariable( $name ),
				                       $this->memory->fromID( $value )->render()->wrap( ' = ', ';' ) ) );

			return $table->render()->indent()->wrapLines( "local variables:" );
		}

		private function prettyPrintExceptionWithoutGlobals()
		{
			return Text::create()
			           ->addLines( $this->prettyPrintExceptionHeader() )
			           ->addLines( $this->prettyPrintLocalVariables() )
			           ->addLines( $this->prettyPrintStackTrace() )
			           ->addLines( $this->prettyPrintPreviousException() );
		}

		private function prettyPrintExceptionHeader()
		{
			return Text::create( "$this->class $this->code in $this->file:$this->line" )
			           ->addLines( Text::create( $this->message )->indent( 2 )->wrapLines() );
		}

		private function prettyPrintPreviousException()
		{
			if ( $this->previous === null )
				return new Text;

			return $this->previous->prettyPrintExceptionWithoutGlobals()->indent( 2 )
			                      ->wrapLines( "previous exception:" );
		}

		private function prettyPrintStackTrace()
		{
			if ( !$this->settings()->showExceptionStackTrace()->get() )
				return new Text;

			$result = new Text;
			$i      = 1;

			foreach ( $this->stack as $stackFrame )
			{
				$result->addLines( $stackFrame->render( $i ) );
				$i++;
			}

			return $result->addLine( "#$i {main}" )->indent()->wrapLines( "stack trace:" );
		}

		private function settings() { return $this->memory->settings(); }
	}

	final class StackFrame
	{
		private $memory, $type, $function, $object, $class, $args, $file, $line;

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

			return new self( $memory, $type, $function, $object, $class, $args, $file, $line );
		}

		/**
		 * @param \PrettyPrinter\Memory $memory
		 * @param string|null           $type
		 * @param string|null           $function
		 * @param int|null              $object
		 * @param string|null           $class
		 * @param int[]|null            $args
		 * @param string|null           $file
		 * @param int|null              $line
		 */
		function __construct( Memory $memory, $type, $function, $object, $class, $args, $file, $line )
		{
			$this->type     = $type;
			$this->function = $function;
			$this->object   = $object;
			$this->class    = $class;
			$this->args     = $args;
			$this->file     = $file;
			$this->line     = $line;
			$this->memory   = $memory;
		}
		
		function render( $i )
		{
			return Text::create()
			           ->addLine( "#$i $this->file:$this->line" )
			           ->addLines( $this->prettyPrintFunctionCall()->indent( 3 ) );
		}

		private function prettyPrintFunctionCall()
		{
			return Text::create()
			           ->appendLines( $this->prettyPrintFunctionObject() )
			           ->append( "$this->type" )
			           ->append( "$this->function" )
			           ->appendLines( $this->prettyPrintFunctionArgs() )
			           ->append( ';' );
		}

		private function prettyPrintFunctionObject()
		{
			if ( $this->object === null )
				return new Text( "$this->class" );

			return $this->memory->fromID( $this->object )->render();
		}

		private function prettyPrintFunctionArgs()
		{
			if ( $this->args === null )
				return new Text( '( ? )' );

			if ( empty( $args ) )
				return new Text( '()' );

			$pretties    = array();
			$isMultiLine = false;
			$result      = new Text;

			foreach ( $args as $arg )
			{
				$pretty      = $this->memory->fromID( $arg )->render();
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
		static function reflect( Memory $memory, Variable $var )
		{
			$class    = $var->className();
			$function = $var->functionName();
			$name     = $var->name();
			$value    = $memory->toID( $var->value() );
			$access   = $var->access();

			return new self( $memory, $class, $function, $name, $value, $access );
		}

		private $memory, $class, $function, $name, $value, $access;

		/**
		 * @param Memory      $memory
		 * @param string|null $class
		 * @param string|null $function
		 * @param string      $name
		 * @param int         $value
		 * @param string|null $access
		 */
		function __construct( Memory $memory, $class, $function, $name, $value, $access )
		{
			$this->memory   = $memory;
			$this->class    = $class;
			$this->function = $function;
			$this->name     = $name;
			$this->value    = $value;
			$this->access   = $access;
		}

		function value() { return $this->value; }

		function prettyPrint( Memory $memory )
		{
			return $this->prefix()->appendLines( $memory->prettyPrintVariable( $this->name ) );
		}

		private function prefix()
		{
			if ( isset( $this->class ) && isset( $this->function ) )
				return new Text( "function $this->class::$this->function()::static " );
			
			if ( isset( $this->class ) )
				return new Text( "$this->access static $this->class::" );
			
			if ( isset( $this->function ) )
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

		function render()
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

		function render() { return new Text( "$this->int" ); }

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
		function render() { return new Text( 'null' ); }

		function type() { return 'null'; }

		function toString() { return ''; }
	}

	final class Object extends Value
	{
		/**
		 * @var object
		 */
		private $object;

		function render()
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

					$properties[ ] = array(
						'name'   => $property->name,
						'value'  => $this->toID( $property->getValue( $this->object ) ),
						'access' => Exception::propertyOrMethodAccess( $property ),
						'class'  => $property->class,
					);
				}
			}

			return $this->render2( get_class( $this->object ), $properties );
		}

		/**
		 * @param string $class
		 * @param array  $properties
		 *
		 * @return Text
		 */
		function render2( $class, array $properties )
		{
			$maxProperties = $this->settings()->maxObjectProperties()->get();
			$numProperties = 0;
			$table         = new Table;

			foreach ( $properties as $property )
			{
				$numProperties++;

				if ( $table->count() >= $maxProperties )
					continue;

				$value  = $property[ 'value' ];
				$name   = $property[ 'name' ];
				$access = $property[ 'access' ];

				$table->addRow( array( $this->prettyPrintVariable( $name )->prepend( "$access " ),
				                       $this->renderFromID( $value )->wrap( ' = ', ';' ) ) );
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

	final class Resource extends Value
	{
		private $resource;

		function render()
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

		function render()
		{
			$string   = $this->string;
			$settings = $this->settings();

			$characterEscapeCache = array( "\\" => '\\\\',
			                               "\$" => '\$',
			                               "\r" => '\r',
			                               "\v" => '\v',
			                               "\f" => '\f',
			                               "\"" => '\"' );

			$characterEscapeCache[ "\t" ] = $settings->escapeTabsInStrings()->get() ? '\t' : "\t";
			$characterEscapeCache[ "\n" ] = $settings->splitMultiLineStrings()->get() ? <<<'s'
\n" .
"
s
					: '\n';

			$escaped = '';
			$length  = min( strlen( $string ), $this->settings()->maxStringLength()->get() );

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
		function render() { return new Text( 'unknown type' ); }

		function type() { return 'unknown'; }

		function toString() { return ''; }
	}
}

