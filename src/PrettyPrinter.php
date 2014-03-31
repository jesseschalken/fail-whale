<?php

namespace ErrorHandler;

final class PrettyPrinter {
    public $escapeTabsInStrings = false;
    public $showExceptionGlobalVariables = true;
    public $showExceptionLocalVariables = true;
    public $showExceptionStackTrace = true;
    public $splitMultiLineStrings = true;
    public $showExceptionSourceCode = true;

    function assertPrettyIs($value, $expectedPretty) {
        \PHPUnit_Framework_TestCase::assertEquals($expectedPretty, $this->prettyPrint($value));
    }

    function assertPrettyRefIs(&$ref, $expectedPretty) {
        \PHPUnit_Framework_TestCase::assertEquals($expectedPretty, $this->prettyPrintRef($ref));
    }

    function prettyPrint($value) {
        return Value::introspect($value)->toString($this);
    }

    function prettyPrintException(\Exception $exception) {
        return Value::introspectException($exception)->toString($this);
    }

    function prettyPrintRef(&$ref) {
        return Value::introspectRef($ref)->toString($this);
    }
}

class PrettyPrinterVisitor implements ValueVisitor {
    private $settings;
    private $arraysRendered = array();
    private $objectsRendered = array();

    function __construct(PrettyPrinter $settings) {
        $this->settings = $settings;
    }

    /**
     * @param ValueImpl $v
     *
     * @return Text
     */
    private function render(ValueImpl $v) { return $v->acceptVisitor($this); }

    function visitArray(ValueArray $array) {
        $rendered =& $this->arraysRendered[$array->id()];

        if ($rendered)
            return new Text('*recursion*');

        $rendered = true;

        $entries       = $array->entries();
        $isAssociative = $array->isAssociative();
        $numEntries    = $array->entriesMissing();
        $numMissing    = count($entries) - $numEntries;

        if ($numEntries == 0)
            return new Text("array()");

        $rows = array();

        foreach ($entries as $keyValuePair) {
            $key   = $this->render($keyValuePair->key());
            $value = $this->render($keyValuePair->value());

            if (count($rows) != $numEntries - 1)
                $value->append(',');

            $rows[] = $isAssociative
                ? array($key, $value->prepend(' => '))
                : array($value);
        }

        $rendered = false;

        $result = Text::table($rows);

        if ($numMissing != 0)
            $result->addLine("$numMissing bytesMissing entries");

        return $result->wrap("array( ", " )");
    }

    /**
     * @param ValueException $e
     *
     * @return Text
     */
    private function renderException(ValueException $e) {
        $location = $e->location();

        $text = new Text("{$e->className()} {$e->code()} in {$location->file()}:{$location->line()}\n");
        $text->addLine();
        $text1 = new Text($e->message());
        $text->addLines($text1->indent(2));
        $text->addLine();

        if ($this->settings->showExceptionSourceCode) {
            $sourceCode = $location->sourceCode();

            $t = !$sourceCode
                ? new Text('not available')
                : $this->renderSourceCode($sourceCode, $location->line());

            $text->addLine("source code:");
            $text->addLines($t->indent());
            $text->addLine();
        }

        if ($this->settings->showExceptionLocalVariables) {
            $locals = $e->locals();

            if (!is_array($locals)) {
                $t = new Text('not available');
            } else {
                $prefixes = array_fill(0, count($locals), '');
                $t        = $this->renderVariables($locals, 'none', $e->localsMissing(), $prefixes);
            }

            $text->addLine("local variables:");
            $text->addLines($t->indent());
            $text->addLine();
        }

        if ($this->settings->showExceptionStackTrace) {
            $text->addLine("stack trace:");
            $text->addLines($this->renderExceptionStack($e)->indent());
            $text->addLine();
        }

        $previous = $e->previous();
        $previous = $previous instanceof ValueException ? $this->renderException($previous) : new Text('none');

        $text->addLine("previous exception:");
        $text->addLines($previous->indent(2));
        $text->addLine();

        return $text;
    }

    /**
     * @param string[] $code
     * @param int      $line
     *
     * @return Text
     */
    private function renderSourceCode(array $code, $line) {
        $rows = array();

        foreach ($code as $codeLine => $codeText) {
            $rows[] = array(new Text($codeLine == $line ? "> " : ''),
                            new Text("$codeLine "),
                            new Text($codeText));
        }

        return Text::table($rows);
    }

    /**
     * @param ValueVariable[] $variables
     * @param string          $noneText
     * @param int             $total
     * @param string[]        $prefixes
     *
     * @return Text
     */
    private function renderVariables(array $variables, $noneText, $total, array $prefixes) {
        if ($total == 0)
            return new Text($noneText);

        $rows = array();

        foreach ($variables as $k => $variable) {
            $text   = new Text($prefixes[$k]);
            $rows[] = array(
                $text->appendLines($this->renderVariable($variable->name())),
                $this->render($variable->value())->wrap(' = ', ';'),
            );
        }

        $result  = Text::table($rows);
        $missing = $total - count($rows);

        if ($missing != 0)
            $result->addLine("$missing bytesMissing");

        return $result;
    }

    private function renderExceptionStack(ValueException $exception) {
        $text = new Text;
        $i    = 1;

        $stack = $exception->stack();

        foreach ($stack as $frame) {
            $location = $frame->location();
            $location = $location instanceof ValueCodeLocation
                ? "{$location->file()}:{$location->line()}"
                : '[internal functionName]';
            $text->addLine("#$i {$location}");
            $text->addLines($this->renderExceptionStackFrame($frame)->append(';')->indent(3));
            $text->addLine();
            $i++;
        }

        $missing = $exception->stackMissing() - count($stack);
        if ($missing != 0)
            $text->addLine("$missing bytesMissing");
        else
            $text->addLine("#$i {main}");

        return $text;
    }

    private function renderExceptionStackFrame(ValueStackFrame $frame) {
        $prefix = $this->renderExceptionStackFramePrefix($frame);
        $args   = $this->renderExceptionStackFrameArgs($frame);

        return $prefix->append($frame->functionName())->appendLines($args);
    }

    function visitException(ValueException $exception) {
        $text = $this->renderException($exception);

        if ($this->settings->showExceptionGlobalVariables) {
            $globals = $exception->globals();

            if ($globals instanceof ValueGlobals) {
                $superGlobals = array(
                    'GLOBALS',
                    '_SERVER',
                    '_GET',
                    '_POST',
                    '_FILES',
                    '_COOKIE',
                    '_SESSION',
                    '_REQUEST',
                    '_ENV',
                );

                $variables = array();
                $prefixes  = array();

                foreach ($globals->staticProperties() as $p) {
                    $variables[] = $p;
                    $prefixes[]  = "{$p->access()} static {$p->className()}::";
                }

                foreach ($globals->staticVariables() as $p) {
                    $class       = $p->className();
                    $function    = $p->functionName();
                    $function    = $class ? "$class::$function" : $function;
                    $prefixes[]  = "functionName $function()::static ";
                    $variables[] = $p;
                }

                foreach ($globals->globalVariables() as $v) {
                    $variables[] = $v;
                    $prefixes[]  = in_array($v->name(), $superGlobals) ? '' : 'global ';
                }

                $total = $globals->staticPropertiesMissing() +
                         $globals->staticVariablesMissing() +
                         $globals->globalVariablesMissing();

                $t = $this->renderVariables($variables, 'none', $total, $prefixes);
            } else {
                $t = new Text('not available');
            }

            $text->addLine("global variables:");
            $text->addLines($t->indent());
            $text->addLine();
        }

        return $text;
    }

    function visitObject(ValueObject $object) {
        $rendered =& $this->objectsRendered[$object->id()];

        if ($rendered)
            return new Text('*recursion*');

        $rendered = true;

        $properties    = $object->properties();
        $class         = $object->className();
        $numProperties = $object->propertiesMissing();

        if ($numProperties == 0) {
            $result = new Text("new $class {}");
        } else {
            $prefixes = array();

            foreach ($properties as $prop)
                $prefixes[] = "{$prop->access()} ";

            $result = $this->renderVariables($properties, '', $numProperties, $prefixes)
                           ->indent(2)->wrapLines("new $class {", "}");
        }

        $rendered = false;

        return $result;
    }

    /**
     * @param string $string
     *
     * @return Text
     */
    private function renderString($string) {
        $characterEscapeCache = array(
            "\\" => '\\\\',
            "\$" => '\$',
            "\r" => '\r',
            "\v" => '\v',
            "\f" => '\f',
            "\"" => '\"',
            "\t" => $this->settings->escapeTabsInStrings ? '\t' : "\t",
            "\n" => $this->settings->splitMultiLineStrings ? "\\n\" .\n\"" : '\n',
        );

        $escaped = '';
        $length  = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $char        = $string[$i];
            $charEscaped =& $characterEscapeCache[$char];

            if (!isset($charEscaped)) {
                $ord         = ord($char);
                $charEscaped = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr('00' . dechex($ord), -2);
            }

            $escaped .= $charEscaped;
        }

        return new Text("\"$escaped\"");
    }

    /**
     * @param string $name
     *
     * @return Text
     */
    private function renderVariable($name) {
        if (preg_match("/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name))
            return new Text("$$name");
        else
            return $this->renderString($name)->wrap('${', '}');
    }

    function visitString(ValueString $string) {
        $result     = $this->renderString($string->bytes());
        $numMissing = $string->bytesMissing() - strlen($string->bytes());

        if ($numMissing != 0)
            $result->append(" $numMissing more bytes...");

        return $result;
    }

    function visitInt($int) {
        return new Text("$int");
    }

    function visitNull() {
        return new Text('null');
    }

    function visitUnknown() {
        return new Text('unknown type');
    }

    function visitFloat($float) {
        $int = (int)$float;

        return new Text("$int" === "$float" ? "$float.0" : "$float");
    }

    function visitResource(ValueResource $resource) {
        return new Text("{$resource->type()}");
    }

    function visitBool($bool) {
        return new Text($bool ? 'true' : 'false');
    }

    private function renderExceptionStackFrameArgs(ValueStackFrame $frame) {
        $args = $frame->arguments();

        if (!is_array($args))
            return new Text("( ? )");

        if ($args === array())
            return new Text("()");

        $pretties    = array();
        $isMultiLine = false;

        foreach ($args as $arg) {
            $pretty      = $this->render($arg);
            $isMultiLine = $isMultiLine || $pretty->count() > 1;
            $pretties[]  = $pretty;
        }

        $result = new Text;

        foreach ($pretties as $k => $pretty) {
            if ($isMultiLine)
                $result->addLines($pretty);
            else
                $result->appendLines($pretty);

            if ($k + 1 == $frame->argumentsMissing())
                $result->append(', ');
        }

        $numMissing = $frame->argumentsMissing() - count($args);
        if ($numMissing != 0)
            if ($isMultiLine)
                $result->addLine("$numMissing more...");
            else
                $result->append("$numMissing more...");

        return $result->wrap("( ", " )");
    }

    private function renderExceptionStackFramePrefix(ValueStackFrame $frame) {
        $object = $frame->object();
        $class  = $frame->className();

        if ($object instanceof ValueObject)
            return $this->visitObject($object)->append('->');
        else if ($class)
            return new Text($frame->isStatic() ? "$class::" : "$class->");
        else
            return new Text;
    }
}

class Text {
    /**
     * @param array $rows
     *
     * @return self
     */
    static function table(array $rows) {
        $columnWidths = array();

        /** @var $cell self */
        foreach (self::flipArray($rows) as $colNo => $column) {
            $width = 0;

            foreach ($column as $cell)
                $width = max($width, $cell->width());

            $columnWidths[$colNo] = $width;
        }

        $result = new self;

        foreach ($rows as $cells) {
            $row        = new self;
            $lastColumn = count($cells) - 1;

            foreach ($cells as $column => $cell) {
                $cell = clone $cell;

                if ($column !== $lastColumn)
                    $cell->padWidth($columnWidths[$column]);

                $row->appendLines($cell);
            }

            $result->addLines($row);
        }

        return $result;
    }

    private static function flipArray(array $x) {
        $result = array();

        foreach ($x as $k1 => $v1)
            foreach ($v1 as $k2 => $v2)
                $result[$k2][$k1] = $v2;

        return $result;
    }

    /** @var string[] */
    private $lines;

    function __construct($text = '') {
        $lines = explode("\n", $text);

        if ($lines && $lines[count($lines) - 1] === "")
            array_pop($lines);

        $this->lines = $lines;
    }

    function toString() {
        $text = join("\n", $this->lines);

        return $this->lines ? "$text\n" : $text;
    }

    /**
     * @param string $line
     *
     * @return Text
     */
    function addLine($line = "") {
        return $this->addLines(new self($line));
    }

    /**
     * @param Text $add
     *
     * @return Text
     */
    function addLines(self $add) {
        foreach ($add->lines as $line)
            $this->lines[] = $line;

        return $this;
    }

    function addLinesBefore(self $addBefore) {
        return $this->addLines($this->swapLines($addBefore));
    }

    function append($string) {
        return $this->appendLines(new self($string));
    }

    /**
     * @param Text $append
     *
     * @return Text
     */
    function appendLines(self $append) {
        $space = str_repeat(' ', $this->width());

        foreach ($append->lines as $k => $line)
            if ($k == 0 && $this->lines)
                $this->lines[count($this->lines) - 1] .= $line;
            else
                $this->lines[] = $space . $line;

        return $this;
    }

    function count() { return count($this->lines); }

    /**
     * @param int $times
     *
     * @return Text
     */
    function indent($times = 1) {
        $space = str_repeat('  ', $times);

        foreach ($this->lines as $k => $line)
            if ($line !== '')
                $this->lines[$k] = $space . $line;

        return $this;
    }

    function padWidth($width) {
        return $this->append(str_repeat(' ', $width - $this->width()));
    }

    /**
     * @param $string
     *
     * @return Text
     */
    function prepend($string) {
        return $this->prependLines(new self($string));
    }

    function prependLine($line = "") {
        return $this->addLines($this->swapLines(new self($line)));
    }

    function prependLines(self $lines) {
        return $this->appendLines($this->swapLines($lines));
    }

    function swapLines(self $other) {
        $clone       = clone $this;
        $this->lines = $other->lines;

        return $clone;
    }

    function width() {
        return $this->lines ? strlen($this->lines[count($this->lines) - 1]) : 0;
    }

    function wrap($prepend, $append) {
        return $this->prepend($prepend)->append($append);
    }

    function wrapLines($prepend = '', $append = '') {
        return $this->prependLine($prepend)->addLine($append);
    }
}
