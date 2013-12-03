<?php

namespace PrettyPrinter
{
    use PrettyPrinter\Introspection\TypeAny;
    use PrettyPrinter\Settings\Bool;
    use PrettyPrinter\Settings\Number;
    use PrettyPrinter\Values\ValueException;
    use PrettyPrinter\Values\ValuePool;

    final class PrettyPrinter
    {
        static function create() { return new self; }

        private $escapeTabsInStrings;
        private $splitMultiLineStrings;
        private $maxObjectProperties;
        private $maxArrayEntries;
        private $maxStringLength;
        private $showExceptionLocalVariables;
        private $showExceptionGlobalVariables;
        private $showExceptionStackTrace;

        function __construct()
        {
            $this->escapeTabsInStrings          = new Bool( $this, false );
            $this->splitMultiLineStrings        = new Bool( $this, true );
            $this->maxObjectProperties          = new Number( $this, PHP_INT_MAX );
            $this->maxArrayEntries              = new Number( $this, PHP_INT_MAX );
            $this->maxStringLength              = new Number( $this, PHP_INT_MAX );
            $this->showExceptionLocalVariables  = new Bool( $this, true );
            $this->showExceptionGlobalVariables = new Bool( $this, true );
            $this->showExceptionStackTrace      = new Bool( $this, true );
        }

        function escapeTabsInStrings() { return $this->escapeTabsInStrings; }

        function splitMultiLineStrings() { return $this->splitMultiLineStrings; }

        function maxObjectProperties() { return $this->maxObjectProperties; }

        function maxArrayEntries() { return $this->maxArrayEntries; }

        function maxStringLength() { return $this->maxStringLength; }

        function showExceptionLocalVariables() { return $this->showExceptionLocalVariables; }

        function showExceptionGlobalVariables() { return $this->showExceptionGlobalVariables; }

        function showExceptionStackTrace() { return $this->showExceptionStackTrace; }

        function prettyPrint( $value )
        {
            return $this->prettyPrintRef( $value );
        }

        function prettyPrintRef( &$ref )
        {
            $any = new TypeAny( new ValuePool );

            return $any->addToPool( $ref )->render( $this )->setHasEndingNewline( false )->__toString();
        }

        function prettyPrintExceptionInfo( ValueException $e )
        {
            return $e->render( $this )->__toString();
        }

        function prettyPrintException( \Exception $e )
        {
            $any = new TypeAny( new ValuePool );

            return $any->addExceptionToPool( $e )->render( $this )->__toString();
        }

        function assertPrettyIs( $value, $expectedPretty )
        {
            return $this->assertPrettyRefIs( $value, $expectedPretty );
        }

        function assertPrettyRefIs( &$ref, $expectedPretty )
        {
            \PHPUnit_Framework_TestCase::assertEquals( $expectedPretty, $this->prettyPrintRef( $ref ) );

            return $this;
        }
    }
}
