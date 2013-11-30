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

			if ( !isset( $this->cache[ $type ][ $string ] ) )
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
			$array = $this->array;

			if ( empty( $array ) )
				return new Text( 'array()' );

			$maxEntries    = $this->settings()->maxArrayEntries()->get();
			$isAssociative = ArrayUtil::isAssoc( $array );
			$table         = new Table;

			foreach ( $array as $k => &$v )
			{
				if ( $table->count() >= $maxEntries )
					break;

				$value = $this->prettyPrintRef( $v );

				if ( $table->count() != count( $array ) - 1 )
					$value->append( ',' );

				$table->addRow( $isAssociative
						                ? array( $this->prettyPrint( $k ), $value->prepend( ' => ' ) )
						                : array( $value ) );
			}

			$result = $table->render();

			if ( $table->count() != count( $array ) )
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

		/**
		 * @return Text
		 */
		function render()
		{
			return $this->prettyPrintExceptionWithoutGlobals( $this->exception )
			            ->addLines( $this->prettyPrintGlobalState( $this->exception ) );
		}

		private function prettyPrintGlobalState( ExceptionInfo $exception )
		{
			if ( !$this->settings()->showExceptionGlobalVariables()->get() )
				return new Text;

			return $this->prettyPrintGlobalVariables( $exception )->indent()->wrapLines( 'global variables:' );
		}

		private function prettyPrintGlobalVariables( ExceptionInfo $exception )
		{
			$globals = $exception->globalVariables();

			if ( empty( $globals ) )
				return new Text( 'none' );

			$table = new Table;

			foreach ( $globals as $global )
				$table->addRow( array( $global->prettyPrint( $this->memory() ),
				                       $this->prettyPrintRef( $global->value() )->wrap( ' = ', ';' ) ) );

			return $table->render();
		}

		private function prettyPrintLocalVariables( ExceptionInfo $exception )
		{
			if ( !$this->settings()->showExceptionLocalVariables()->get() )
				return new Text;

			if ( $exception->localVariables() === null )
				return new Text;

			$table = new Table;

			foreach ( $exception->localVariables() as $name => $value )
				$table->addRow( array( $this->prettyPrintVariable( $name ),
				                       $this->prettyPrintRef( $value )->wrap( ' = ', ';' ) ) );

			return $table->render()->indent()->wrapLines( "local variables:" );
		}

		private function prettyPrintExceptionWithoutGlobals( ExceptionInfo $e )
		{
			return Text::create()
			           ->addLines( $this->prettyPrintExceptionHeader( $e ) )
			           ->addLines( $this->prettyPrintLocalVariables( $e ) )
			           ->addLines( $this->prettyPrintStackTrace( $e ) )
			           ->addLines( $this->prettyPrintPreviousException( $e ) );
		}

		private function prettyPrintExceptionHeader( ExceptionInfo $e )
		{
			$class = $e->exceptionClassName();
			$code  = $e->code();
			$file  = $e->file();
			$line  = $e->line();

			return Text::create( "$class $code in $file:$line" )
			           ->addLines( Text::create( $e->message() )->indent( 2 )->wrapLines() );
		}

		private function prettyPrintPreviousException( ExceptionInfo $exception )
		{
			if ( $exception->previous() === null )
				return new Text;

			return $this->prettyPrintExceptionWithoutGlobals( $exception->previous() )->indent( 2 )
			            ->wrapLines( "previous exception:" );
		}

		private function prettyPrintStackTrace( ExceptionInfo $exception )
		{
			if ( !$this->settings()->showExceptionStackTrace()->get() )
				return new Text;

			$result = new Text;
			$i      = 1;

			foreach ( $exception->stackTrace() as $stackFrame )
			{
				$result->addLine( "#$i $stackFrame[file]:$stackFrame[line]" );
				$result->addLines( $this->prettyPrintFunctionCall( $stackFrame )->indent( 3 ) );
				$result->addLine();
				$i++;
			}

			return $result->addLine( "#$i {main}" )->indent()->wrapLines( "stack trace:" );
		}

		private function prettyPrintFunctionCall( array $stackFrame )
		{
			return Text::create()
			           ->appendLines( $this->prettyPrintFunctionObject( $stackFrame ) )
			           ->append( ArrayUtil::get( $stackFrame, 'type', '' ) )
			           ->append( ArrayUtil::get( $stackFrame, 'function', '' ) )
			           ->appendLines( $this->prettyPrintFunctionArgs( $stackFrame ) )
			           ->append( ';' );
		}

		private function prettyPrintFunctionObject( array $stackFrame )
		{
			$object = ArrayUtil::get( $stackFrame, 'object' );

			if ( !isset( $object ) )
				return new Text( ArrayUtil::get( $stackFrame, 'class', '' ) );

			return $this->prettyPrint( $object );
		}

		private function prettyPrintFunctionArgs( array $stackFrame )
		{
			$args = ArrayUtil::get( $stackFrame, 'args' );

			if ( !isset( $args ) )
				return new Text( '( ? )' );

			if ( empty( $args ) )
				return new Text( '()' );

			$pretties    = array();
			$isMultiLine = false;
			$result      = new Text;

			foreach ( $args as &$arg )
			{
				$pretty      = $this->prettyPrintRef( $arg );
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

		function type() { return 'exception'; }

		function toString() { return spl_object_hash( $this->exception ); }
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
			$object = $this->object;
			$class  = get_class( $object );

			$maxProperties = $this->settings()->maxObjectProperties()->get();
			$numProperties = 0;
			$table         = new Table;

			for ( $reflection = new \ReflectionObject( $object );
			      $reflection !== false;
			      $reflection = $reflection->getParentClass() )
			{
				foreach ( $reflection->getProperties() as $property )
				{
					if ( $property->isStatic() || $property->class !== $reflection->name )
						continue;

					$numProperties++;

					if ( $table->count() >= $maxProperties )
						continue;

					$property->setAccessible( true );

					$access = Exception::propertyOrMethodAccess( $property );

					$table->addRow( array( $this->prettyPrintVariable( $property->name )->prepend( "$access " ),
					                       $this->prettyPrint( $property->getValue( $object ) )->wrap( ' = ', ';' ) ) );
				}
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

