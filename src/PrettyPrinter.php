<?php

namespace PrettyPrinter
{
    use PrettyPrinter\Introspection\Introspection;
    use PrettyPrinter\Utils\Table;
    use PrettyPrinter\Utils\Text;
    use PrettyPrinter\Values\ValuePool;

    final class PrettyPrinter
    {
        static function create() { return new self; }

        private $escapeTabsInStrings = false;
        private $maxArrayEntries = INF;
        private $maxObjectProperties = INF;
        private $maxStringLength = INF;
        private $showExceptionGlobalVariables = true;
        private $showExceptionLocalVariables = true;
        private $showExceptionStackTrace = true;
        private $splitMultiLineStrings = true;

        function __construct() { }

        function assertPrettyIs( $value, $expectedPretty )
        {
            return $this->assertPrettyRefIs( $value, $expectedPretty );
        }

        function assertPrettyRefIs( &$ref, $expectedPretty )
        {
            \PHPUnit_Framework_TestCase::assertEquals( $expectedPretty, $this->prettyPrintRef( $ref ) );

            return $this;
        }

        function prettyPrint( $value )
        {
            return $this->prettyPrintRef( $value );
        }

        function prettyPrintException( \Exception $e )
        {
            $introspection = new Introspection( new ValuePool );

            return $introspection->wrapException( $e )->introspect()->render( $this )->__toString();
        }

        function prettyPrintRef( &$ref )
        {
            $introspection = new Introspection( new ValuePool );

            return $introspection->wrapRef( $ref )->introspect()->render( $this )->__toString();
        }
        
        function renderReference( Values\ValuePoolReference $reference )
        {
            return $reference->get()->render( $this );
        }

        function renderArray( Values\ValueArray $object )
        {
            if ( $object->keyValuePairs() === array() )
                return new Text( 'array()' );

            $table = new Table;

            foreach ( $object->keyValuePairs() as $keyValuePair )
            {
                if ( ( $table->count() + 1 ) > $this->maxArrayEntries )
                    break;

                $key   = $keyValuePair->key()->render( $this );
                $value = $keyValuePair->value()->render( $this );

                if ( $table->count() != count( $object->keyValuePairs() ) - 1 )
                    $value->append( ',' );

                $table->addRow( $object->isAssociative()
                                        ? array( $key, $value->prepend( ' => ' ) )
                                        : array( $value ) );
            }

            $result = $table->render();

            if ( $table->count() < count( $object->keyValuePairs() ) )
                $result->addLine( '...' );

            $result->wrap( 'array( ', ' )' );

            return $result;
        }

        /**
         * @param bool $bool
         *
         * @return Text
         */
        function renderBool( $bool ) { return new Text( $bool ? 'true' : 'false' ); }

        /**
         * @param Values\ValueException $exception
         *
         * @return Text
         */
        function renderException( Values\ValueException $exception )
        {
            $text = $this->renderExceptionWithoutGlobals( $exception );

            if ( $this->showExceptionGlobalVariables )
            {
                $text->addLine( "global variables:" );
                $text->addLines( $this->renderExceptionGlobals( $exception )->indent() );
                $text->addLine();
            }

            return $text;
        }

        private function renderExceptionGlobals( Values\ValueException $exception )
        {
            $superGlobals = array( '_POST', '_GET', '_SESSION', '_COOKIE', '_FILES', '_REQUEST', '_ENV', '_SERVER' );

            $table = new Table;

            foreach ( $exception->globals() as $global )
            {
                if ( $global->className() !== null && $global->functionName() !== null )
                {
                    $prefix = new Text( "function {$global->className()}::{$global->functionName()}()::static " );
                }
                else if ( $global->className() !== null )
                {
                    $prefix = new Text( "{$global->access()} static {$global->className()}::" );
                }
                else if ( $global->functionName() !== null )
                {
                    $prefix = new Text( "function {$global->functionName()}()::static " );
                }
                else
                {
                    $prefix = new Text( in_array( $global->variableName(), $superGlobals, true ) ? '' : 'global ' );
                }

                $table->addRow( array( $prefix->appendLines( $this->renderVariable( $global->variableName() ) ),
                                       $global->value()->render( $this )->wrap( ' = ', ';' ) ) );
            }

            return $table->count() > 0 ? $table->render() : new Text( 'none' );
        }

        private function renderExceptionLocals( Values\ValueException $exception )
        {
            $table = new Table;

            foreach ( $exception->locals() as $name => $value )
            {
                $table->addRow( array( $this->renderVariable( $name ),
                                       $value->render( $this )->wrap( ' = ', ';' ) ) );
            }

            return $table->count() > 0 ? $table->render() : new Text( 'none' );
        }

        private function renderExceptionStack( Values\ValueException $exception )
        {
            $text = new Text;
            $i    = 1;

            foreach ( $exception->stack() as $frame )
            {
                $text->addLines( $this->renderExceptionStackFrame( $i, $frame ) );
                $text->addLine();
                $i++;
            }

            $text->addLine( "#$i {main}" );

            return $text;
        }

        private function renderExceptionStackFrame( $i, Values\ValueExceptionStackFrame $frame )
        {
            $text = new Text;
            $text->addLine( "#$i {$frame->file()}:{$frame->line()}" );
            $text->addLines( $this->renderExceptionStackFrameFunctionCall( $frame )->indent( 3 ) );

            return $text;
        }

        private function renderExceptionStackFrameArgs( Values\ValueExceptionStackFrame $frame )
        {
            if ( $frame->args() === null )
                return new Text( '( ? )' );

            if ( $frame->args() === array() )
                return new Text( '()' );

            $pretties    = array();
            $isMultiLine = false;
            $result      = new Text;

            foreach ( $frame->args() as $arg )
            {
                $pretty      = $arg->render( $this );
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

        private function renderExceptionStackFrameFunctionCall( Values\ValueExceptionStackFrame $frame )
        {
            $text = new Text;
            $text->appendLines( $this->renderExceptionStackFrameObject( $frame ) );
            $text->append( $frame->type() );
            $text->append( $frame->functionName() );
            $text->appendLines( $this->renderExceptionStackFrameArgs( $frame ) );
            $text->append( ';' );

            return $text;
        }

        private function renderExceptionStackFrameObject( Values\ValueExceptionStackFrame $frame )
        {
            if ( $frame->object() === null )
                return new Text;

            return $frame->object()->render( $this );
        }

        private function renderExceptionWithoutGlobals( Values\ValueException $exception )
        {
            $class   = $exception->className();
            $code    = $exception->code();
            $file    = $exception->file();
            $line    = $exception->line();
            $message = $exception->message();

            $text = new Text;
            $text->addLine( "$class $code in $file:$line" );
            $text->addLine();
            $text->addLines( Text::create( $message )->indent( 2 ) );
            $text->addLine();

            if ( $exception->locals() !== null && $this->showExceptionLocalVariables )
            {
                $text->addLine( "local variables:" );
                $text->addLines( $this->renderExceptionLocals( $exception )->indent() );
                $text->addLine();
            }

            if ( $this->showExceptionStackTrace )
            {
                $text->addLine( "stack trace:" );
                $text->addLines( $this->renderExceptionStack( $exception )->indent() );
                $text->addLine();
            }

            if ( $exception->previous() !== null )
            {
                $text->addLine( "previous exception:" );
                $text->addLines( $this->renderExceptionWithoutGlobals( $exception->previous() )->indent( 2 ) );
                $text->addLine();
            }

            return $text;
        }

        /**
         * @param float $float
         *
         * @return Text
         */
        function renderFloat( $float )
        {
            $int = (int) $float;

            return new Text( "$int" === "$float" ? "$float.0" : "$float" );
        }

        /**
         * @param int $int
         *
         * @return Text
         */
        function renderInt( $int ) { return new Text( "$int" ); }

        function renderNull() { return new Text( 'null' ); }

        function renderObject( Values\ValueObject $object )
        {
            if ( $object->getProperties() === null )
                return new Text( "new {$object->getClass()} { ? }" );

            $table = new Table;

            foreach ( $object->getProperties() as $property )
            {
                if ( ( $table->count() + 1 ) > $this->maxObjectProperties )
                    break;

                $value  = $property->value();
                $name   = $property->name();
                $access = $property->access();

                $table->addRow( array( $this->renderVariable( $name )->prepend( "$access " ),
                                       $value->render( $this )->wrap( ' = ', ';' ) ) );
            }

            $result = $table->render();

            if ( $table->count() < count( $object->getProperties() ) )
                $result->addLine( '...' );

            return $result->indent( 2 )->wrapLines( "new {$object->getClass()} {", "}" );
        }

        /**
         * @param string $string
         *
         * @return Text
         */
        function renderString( $string )
        {
            $characterEscapeCache = array( "\\" => '\\\\',
                                           "\$" => '\$',
                                           "\r" => '\r',
                                           "\v" => '\v',
                                           "\f" => '\f',
                                           "\"" => '\"',
                                           "\t" => $this->escapeTabsInStrings ? '\t' : "\t",
                                           "\n" => $this->splitMultiLineStrings ? "\\n\" .\n\"" : '\n' );

            $escaped = '';
            $length  = min( strlen( $string ), $this->maxStringLength );

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

        function renderUnknown() { return new Text( 'unknown type' ); }

        /**
         * @param string $name
         *
         * @return Text
         */
        private function renderVariable( $name )
        {
            if ( preg_match( "/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name ) )
                return new Text( "$$name" );

            return $this->renderString( $name )->wrap( '${', '}' );
        }

        function setEscapeTabsInStrings( $escapeTabsInStrings )
        {
            $this->escapeTabsInStrings = (bool) $escapeTabsInStrings;

            return $this;
        }

        function setMaxArrayEntries( $maxArrayEntries )
        {
            $this->maxArrayEntries = (float) $maxArrayEntries;

            return $this;
        }

        function setMaxObjectProperties( $maxObjectProperties )
        {
            $this->maxObjectProperties = (float) $maxObjectProperties;

            return $this;
        }

        function setMaxStringLength( $maxStringLength )
        {
            $this->maxStringLength = (float) $maxStringLength;

            return $this;
        }

        function setShowExceptionGlobalVariables( $showExceptionGlobalVariables )
        {
            $this->showExceptionGlobalVariables = (bool) $showExceptionGlobalVariables;

            return $this;
        }

        function setShowExceptionLocalVariables( $showExceptionLocalVariables )
        {
            $this->showExceptionLocalVariables = (bool) $showExceptionLocalVariables;

            return $this;
        }

        function setShowExceptionStackTrace( $showExceptionStackTrace )
        {
            $this->showExceptionStackTrace = (bool) $showExceptionStackTrace;

            return $this;
        }

        function setSplitMultiLineStrings( $splitMultiLineStrings )
        {
            $this->splitMultiLineStrings = (bool) $splitMultiLineStrings;

            return $this;
        }
    }
}
