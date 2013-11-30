<?php

namespace PrettyPrinter
{
	use PrettyPrinter\TypeHandlers\Any;

	abstract class TypeHandler
	{
		/** @var Any */
		private $anyHandler;

		function __construct( Any $handler )
		{
			$this->anyHandler = $handler;
		}

		protected final function prettyPrintRef( &$value )
		{
			return $this->anyHandler->handleValue( $value );
		}

		protected final function prettyPrint( $value )
		{
			return $this->anyHandler->handleValue( $value );
		}

		function prettyPrintVariable( $varName )
		{
			return $this->anyHandler->prettyPrintVariable( $varName );
		}

		protected function settings()
		{
			return $this->anyHandler->settings();
		}
	}
}

namespace PrettyPrinter\TypeHandlers
{
	use PrettyPrinter\PrettyPrinter;
	use PrettyPrinter\TypeHandler;
	use PrettyPrinter\Utils\ArrayUtil;
	use PrettyPrinter\Utils\Table;
	use PrettyPrinter\Utils\Text;
	use PrettyPrinter\ExceptionInfo;

	final class Any extends TypeHandler
	{
		private $bool, $int, $float, $string, $array, $object, $resource, $null, $unknown;
		private $variable, $settings;

		function __construct( PrettyPrinter $settings )
		{
			$this->settings = $settings;
			$this->variable = new Variable( $this );
			$this->bool     = new Boolean( $this );
			$this->int      = new Integer( $this );
			$this->float    = new Float( $this );
			$this->string   = new String( $this );
			$this->array    = new Array1( $this );
			$this->object   = new Object( $this );
			$this->resource = new Resource( $this );
			$this->null     = new Null( $this );
			$this->unknown  = new Unknown( $this );

			parent::__construct( $this );
		}

		/**
		 * @param $value
		 *
		 * @return Text
		 */
		function handleValue( &$value )
		{
			if ( is_bool( $value ) )
				return $this->bool->handleBool( $value );

			if ( is_string( $value ) )
				return $this->string->handleString( $value );

			if ( is_int( $value ) )
				return $this->int->handleInteger( $value );

			if ( is_float( $value ) )
				return $this->float->handleFloat( $value );

			if ( is_object( $value ) )
				return $this->object->handleObject( $value );

			if ( is_array( $value ) )
				return $this->array->handleArray( $value );

			if ( is_resource( $value ) )
				return $this->resource->handleResource( $value );

			if ( is_null( $value ) )
				return $this->null->handleNull();

			return $this->unknown->handleUnknown();
		}

		function prettyPrintVariable( $varName )
		{
			return $this->variable->handleVariable( $varName );
		}

		function settings() { return $this->settings; }
	}

	/**
	 * Called "Array1" because "Array" is a reserved word.
	 */
	final class Array1 extends TypeHandler
	{
		/**
		 * @param array $array
		 *
		 * @return Text
		 */
		function handleArray( &$array )
		{
			return $this->prettyPrintArrayDeep( $array );
		}

		private function prettyPrintArrayDeep( array $array )
		{
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
	}

	final class Boolean extends TypeHandler
	{
		/**
		 * @param bool $value
		 *
		 * @return Text
		 */
		function handleBool( $value )
		{
			return new Text( $value ? 'true' : 'false' );
		}
	}

	final class Exception extends TypeHandler
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

		/**
		 * @param ExceptionInfo $exception
		 *
		 * @return \PrettyPrinter\Utils\Text
		 */
		function handleValue( ExceptionInfo $exception )
		{
			return $this->prettyPrintExceptionWithoutGlobals( $exception )
			            ->addLines( $this->prettyPrintGlobalState( $exception ) );
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
				$table->addRow( array( $global->prettyPrint( $this ),
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
	}

	final class Float extends TypeHandler
	{
		/**
		 * @param float $float
		 *
		 * @return Text
		 */
		function handleFloat( $float )
		{
			$int = (int) $float;

			return new Text( "$int" === "$float" ? "$float.0" : "$float" );
		}
	}

	final class Integer extends TypeHandler
	{
		/**
		 * @param int $int
		 *
		 * @return Text
		 */
		function handleInteger( $int )
		{
			return new Text( "$int" );
		}
	}

	final class Null extends TypeHandler
	{
		function handleNull()
		{
			return new Text( 'null' );
		}
	}

	final class Object extends TypeHandler
	{
		/**
		 * @param object $object
		 *
		 * @return Text
		 */
		function handleObject( $object )
		{
			return $this->prettyPrintObject( $object );
		}

		private function prettyPrintObject( $object )
		{
			$class = get_class( $object );

			$maxProperties = $this->maxProperties();
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

		private function maxProperties()
		{
			return $this->settings()->maxObjectProperties()->get();
		}
	}

	final class Resource extends TypeHandler
	{
		/**
		 * @param resource $resource
		 *
		 * @return Text
		 */
		function handleResource( $resource )
		{
			return new Text( get_resource_type( $resource ) );
		}
	}

	final class String extends TypeHandler
	{
		/**
		 * @param string $string
		 *
		 * @return Text
		 */
		function handleString( $string )
		{
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
	}

	final class Unknown extends TypeHandler
	{
		function handleUnknown()
		{
			return new Text( 'unknown type' );
		}
	}

	final class Variable extends TypeHandler
	{
		function handleVariable( $varName )
		{
			if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $varName ) )
				return new Text( "$$varName" );
			else
				return $this->prettyPrint( $varName )->wrap( '${', '}' );
		}
	}
}

