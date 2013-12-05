<?php

namespace PrettyPrinter
{
    use PrettyPrinter\Introspection\Introspection;
    use PrettyPrinter\Values\ValueException;
    use PrettyPrinter\Values\ValuePool;

    final class PrettyPrinter
    {
        static function create() { return new self; }

        private $escapeTabsInStrings = false;
        private $splitMultiLineStrings = true;
        private $maxObjectProperties = INF;
        private $maxArrayEntries = INF;
        private $maxStringLength = INF;
        private $showExceptionLocalVariables = true;
        private $showExceptionGlobalVariables = true;
        private $showExceptionStackTrace = true;

        function __construct()
        {
        }

        function getEscapeTabsInStrings() { return $this->escapeTabsInStrings; }

        function getSplitMultiLineStrings() { return $this->splitMultiLineStrings; }

        function getMaxObjectProperties() { return $this->maxObjectProperties; }

        function getMaxArrayEntries() { return $this->maxArrayEntries; }

        function getMaxStringLength() { return $this->maxStringLength; }

        function getShowExceptionLocalVariables() { return $this->showExceptionLocalVariables; }

        function getShowExceptionGlobalVariables() { return $this->showExceptionGlobalVariables; }

        function getShowExceptionStackTrace() { return $this->showExceptionStackTrace; }

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

        function prettyPrint( $value )
        {
            return $this->prettyPrintRef( $value );
        }

        function prettyPrintRef( &$ref )
        {
            $any = new Introspection( new ValuePool );

            return $any->wrapRef( $ref )->introspect()->render( $this )->setHasEndingNewline( false )->__toString();
        }

        function prettyPrintExceptionInfo( ValueException $e )
        {
            return $e->render( $this )->__toString();
        }

        function prettyPrintException( \Exception $e )
        {
            $any = new Introspection( new ValuePool );

            return $any->wrapException( $e )->introspect()->render( $this )->__toString();
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
