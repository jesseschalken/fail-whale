<?php

namespace PrettyPrinter {
    use PrettyPrinter\Introspection\Introspection;
    use PrettyPrinter\Utils\Table;
    use PrettyPrinter\Utils\Text;
    use PrettyPrinter\Values\Variable;

    final class PrettyPrinter {
        static function create() { return new self; }

        function text($text = '') { return new Text($text); }

        private $escapeTabsInStrings = false;
        private $maxArrayEntries = INF;
        private $maxObjectProperties = INF;
        private $maxStringLength = INF;
        private $showExceptionGlobalVariables = true;
        private $showExceptionLocalVariables = true;
        private $showExceptionStackTrace = true;
        private $splitMultiLineStrings = true;

        private $printedObjects = array();
        private $printedArrays = array();

        function __construct() { }

        function assertPrettyIs($value, $expectedPretty) {
            return $this->assertPrettyRefIs($value, $expectedPretty);
        }

        function assertPrettyRefIs(&$ref, $expectedPretty) {
            \PHPUnit_Framework_TestCase::assertEquals($expectedPretty, $this->prettyPrintRef($ref));

            return $this;
        }

        function prettyPrint($value) {
            return $this->prettyPrintRef($value);
        }

        function prettyPrintException(\Exception $e) {
            $introspection = new Introspection;

            return $introspection->introspectException($e)->render($this)->toString();
        }

        function prettyPrintRef(&$ref) {
            $introspection = new Introspection;

            return $introspection->introspectRef($ref)->serialuzeUnserialize()->render($this)
                                 ->setHasEndingNewline(false)->toString();
        }

        function renderArray(Values\ValueArray $object) {
            if (isset($this->printedArrays[$object->id()]))
                return $this->text('array( ... )');

            $this->printedArrays[$object->id()] = true;

            if ($object->entries() === array())
                return $this->text('array()');

            $table = new Table;

            foreach ($object->entries() as $keyValuePair) {
                if (($table->count() + 1) > $this->maxArrayEntries)
                    break;

                $key   = $keyValuePair->key()->render($this);
                $value = $keyValuePair->value()->render($this);

                if ($table->count() != count($object->entries()) - 1)
                    $value->append(',');

                $table->addRow($object->isAssociative()
                                   ? array($key, $value->prepend(' => '))
                                   : array($value));
            }

            $result = $table->render();

            if ($table->count() < count($object->entries()))
                $result->addLine('...');

            return $result->wrap('array( ', ' )');
        }

        /**
         * @param Values\ValueException $e
         *
         * @return Text
         */
        function renderException(Values\ValueException $e) {
            $text = $this->text("{$e->className()} {$e->code()} in {$e->file()}:{$e->line()}");
            $text->addLine();
            $text->addLines($this->text($e->message())->indent(2));
            $text->addLine();

            if ($this->showExceptionLocalVariables && $e->locals() !== null) {
                $text->addLine("local variables:");
                $text->addLines($this->renderVariables($e->locals(), 'none', INF)->indent());
                $text->addLine();
            }

            if ($this->showExceptionStackTrace) {
                $text->addLine("stack trace:");
                $text->addLines($this->renderExceptionStack($e)->indent());
                $text->addLine();
            }

            if ($e->previous() !== null) {
                $text->addLine("previous exception:");
                $text->addLines($this->renderException($e->previous())->indent(2));
                $text->addLine();
            }

            return $text;
        }

        /**
         * @param Variable[] $variables
         * @param string     $noneText
         * @param float      $max
         *
         * @return Text
         */
        function renderVariables(array $variables, $noneText, $max) {
            $table = new Table;

            foreach ($variables as $variable) {
                if (($table->count() + 1) > $max)
                    break;

                $table->addRow(array($variable->render($this),
                                     $variable->value()->render($this)->wrap(' = ', ';')));
            }

            if ($table->count() == 0)
                return $this->text($noneText);

            $result = $table->render();

            if ($table->count() < count($variables))
                $result->addLine('...');

            return $result;
        }

        function renderExceptionStack(Values\ValueException $exception) {
            $text = $this->text();
            $i    = 1;

            foreach ($exception->stack() as $frame) {
                $text->addLine("#$i {$frame->location()}");
                $text->addLines($frame->render($this)->append(';')->indent(3));
                $text->addLine();
                $i++;
            }

            return $text->addLine("#$i {main}");
        }

        function renderExceptionWithGlobals(Values\ValueException $exception) {
            $text = $this->renderException($exception);

            if ($this->showExceptionGlobalVariables && $exception->globals() !== null) {
                $text->addLine("global variables:");
                $text->addLines($this->renderVariables($exception->globals(), 'none', INF)->indent());
                $text->addLine();
            }

            return $text;
        }

        function renderObject(Values\ValueObject $object) {
            if (isset($this->printedObjects[$object->id()]))
                return $this->text("new {$object->className()} { ... }");

            $this->printedObjects[$object->id()] = true;

            return $this->renderVariables($object->properties(), '', $this->maxObjectProperties)
                        ->indent(2)
                        ->wrapLines("new {$object->className()} {", "}");
        }

        /**
         * @param string $string
         *
         * @return Text
         */
        function renderString($string) {
            $characterEscapeCache = array(
                "\\" => '\\\\',
                "\$" => '\$',
                "\r" => '\r',
                "\v" => '\v',
                "\f" => '\f',
                "\"" => '\"',
                "\t" => $this->escapeTabsInStrings ? '\t' : "\t",
                "\n" => $this->splitMultiLineStrings ? "\\n\" .\n\"" : '\n',
            );

            $escaped = '';
            $length  = min(strlen($string), $this->maxStringLength);

            for ($i = 0; $i < $length; $i++) {
                $char        = $string[$i];
                $charEscaped =& $characterEscapeCache[$char];

                if (!isset($charEscaped)) {
                    $ord         = ord($char);
                    $charEscaped = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr('00' . dechex($ord), -2);
                }

                $escaped .= $charEscaped;
            }

            return $this->text("\"$escaped" . (strlen($string) > $length ? '...' : '"'));
        }

        /**
         * @param string $name
         *
         * @return Text
         */
        function renderVariable($name) {
            if (preg_match("/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name))
                return $this->text("$$name");

            return $this->renderString($name)->wrap('${', '}');
        }

        function setEscapeTabsInStrings($escapeTabsInStrings) {
            $this->escapeTabsInStrings = (bool)$escapeTabsInStrings;

            return $this;
        }

        function setMaxArrayEntries($maxArrayEntries) {
            $this->maxArrayEntries = (float)$maxArrayEntries;

            return $this;
        }

        function setMaxObjectProperties($maxObjectProperties) {
            $this->maxObjectProperties = (float)$maxObjectProperties;

            return $this;
        }

        function setMaxStringLength($maxStringLength) {
            $this->maxStringLength = (float)$maxStringLength;

            return $this;
        }

        function setShowExceptionGlobalVariables($showExceptionGlobalVariables) {
            $this->showExceptionGlobalVariables = (bool)$showExceptionGlobalVariables;

            return $this;
        }

        function setShowExceptionLocalVariables($showExceptionLocalVariables) {
            $this->showExceptionLocalVariables = (bool)$showExceptionLocalVariables;

            return $this;
        }

        function setShowExceptionStackTrace($showExceptionStackTrace) {
            $this->showExceptionStackTrace = (bool)$showExceptionStackTrace;

            return $this;
        }

        function setSplitMultiLineStrings($splitMultiLineStrings) {
            $this->splitMultiLineStrings = (bool)$splitMultiLineStrings;

            return $this;
        }
    }
}
