<?php

namespace ErrorHandler;

final class PrettyPrinter {
    function text($text = '') { return new PrettyPrinterText($text, "\n"); }

    private $escapeTabsInStrings = false;
    private $maxArrayEntries = INF;
    private $maxObjectProperties = INF;
    private $maxStringLength = INF;
    private $showExceptionGlobalVariables = true;
    private $showExceptionLocalVariables = true;
    private $showExceptionStackTrace = true;
    private $splitMultiLineStrings = true;

    private $valuesReferable = array();

    function assertPrettyIs($value, $expectedPretty) {
        $this->assertPrettyRefIs($value, $expectedPretty);
    }

    function assertPrettyRefIs(&$ref, $expectedPretty) {
        \PHPUnit_Framework_TestCase::assertEquals($expectedPretty, $this->prettyPrintRef($ref));
    }

    function prettyPrint($value) {
        return $this->prettyPrintRef($value);
    }

    function prettyPrintException(\Exception $e) {
        return Value::introspectException($e)->render($this)->toString();
    }

    function prettyPrintRef(&$ref) {
        return Value::introspectRef($ref)->toJsonFromJson()->render($this)->toString();
    }

    function render(Value $v) {
        $id = $v->id();

        if (isset($this->valuesReferable[$id]))
            return $this->text('*recursion*');

        $this->valuesReferable[$id] = true;

        $result = $v->renderImpl($this);

        unset($this->valuesReferable[$id]);

        return $result;
    }

    function renderArray(ValueArray $array) {
        if ($array->entries() === array())
            return $this->text("array()");

        $rows = array();

        foreach ($array->entries() as $keyValuePair) {
            if ((count($rows) + 1) > $this->maxArrayEntries)
                break;

            $key   = $keyValuePair->key()->render($this);
            $value = $keyValuePair->value()->render($this);

            if (count($rows) != count($array->entries()) - 1)
                $value->append(',');

            $rows[] = $array->isAssociative()
                ? array($key, $value->prepend(' => '))
                : array($value);
        }

        $result = $this->renderTable($rows);

        if (count($rows) < count($array->entries()))
            $result->addLine('...');

        return $result->setHasEndingNewline(false)->wrap("array( ", " )");
    }

    /**
     * @param ValueException $e
     *
     * @return PrettyPrinterText
     */
    function renderException(ValueException $e) {
        $text = $this->text("{$e->className()} {$e->code()} in {$e->file()}:{$e->line()}\n");
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
     * @param ValueVariable[] $variables
     * @param string          $noneText
     * @param float           $max
     *
     * @return PrettyPrinterText
     */
    function renderVariables(array $variables, $noneText, $max) {
        $rows = array();

        foreach ($variables as $variable) {
            if ((count($rows) + 1) > $max)
                break;

            $rows[] = array(
                $variable->render($this),
                $variable->value()->render($this)->wrap(' = ', ';'),
            );
        }

        if (count($rows) == 0)
            return $this->text($noneText);

        $result = $this->renderTable($rows);

        if (count($rows) < count($variables))
            $result->addLine('...');

        return $result;
    }

    function renderExceptionStack(ValueException $exception) {
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

    function renderExceptionWithGlobals(ValueException $exception) {
        $text = $this->renderException($exception);

        if ($this->showExceptionGlobalVariables && $exception->globals() !== null) {
            $text->addLine("global variables:");
            $text->addLines($this->renderVariables($exception->globals(), 'none', INF)->indent());
            $text->addLine();
        }

        return $text;
    }

    function renderObject(ValueObject $object) {
        return $this->renderVariables($object->properties(), '', $this->maxObjectProperties)
                    ->setHasEndingNewline(false)
                    ->indent(2)->wrapLines("new {$object->className()} {", "}");
    }

    /**
     * @param string $string
     *
     * @return PrettyPrinterText
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
     * @return PrettyPrinterText
     */
    function renderVariable($name) {
        if (preg_match("/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name))
            return $this->text("$$name");

        return $this->renderString($name)->wrap('${', '}');
    }

    function setEscapeTabsInStrings($escapeTabsInStrings) {
        $this->escapeTabsInStrings = (bool)$escapeTabsInStrings;
    }

    function setMaxArrayEntries($maxArrayEntries) {
        $this->maxArrayEntries = (float)$maxArrayEntries;
    }

    function setMaxObjectProperties($maxObjectProperties) {
        $this->maxObjectProperties = (float)$maxObjectProperties;
    }

    function setMaxStringLength($maxStringLength) {
        $this->maxStringLength = (float)$maxStringLength;
    }

    function setShowExceptionGlobalVariables($showExceptionGlobalVariables) {
        $this->showExceptionGlobalVariables = (bool)$showExceptionGlobalVariables;
    }

    function setShowExceptionLocalVariables($showExceptionLocalVariables) {
        $this->showExceptionLocalVariables = (bool)$showExceptionLocalVariables;
    }

    function setShowExceptionStackTrace($showExceptionStackTrace) {
        $this->showExceptionStackTrace = (bool)$showExceptionStackTrace;
    }

    function setSplitMultiLineStrings($splitMultiLineStrings) {
        $this->splitMultiLineStrings = (bool)$splitMultiLineStrings;
    }

    private function renderTable($rows) {
        return PrettyPrinterText::renderTable($rows, "\n");
    }
}

class PrettyPrinterText {
    /**
     * @param array  $rows
     * @param string $newLineChar
     *
     * @return self
     */
    static function renderTable(array $rows, $newLineChar) {
        $columnWidths = array();

        /** @var $cell self */
        foreach (self::flipArray($rows) as $colNo => $column) {
            $width = 0;

            foreach ($column as $cell)
                $width = max($width, $cell->width());

            $columnWidths[$colNo] = $width;
        }

        $result = new self('', $newLineChar);

        foreach ($rows as $cells) {
            $row        = new self('', $newLineChar);
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

    private $lines, $hasEndingNewLine, $newLineChar;

    function __construct($text, $newLineChar) {
        $this->newLineChar = $newLineChar;
        $this->lines       = explode($this->newLineChar, $text);

        if ($this->hasEndingNewLine = $this->lines[count($this->lines) - 1] === "")
            array_pop($this->lines);
    }

    function toString() {
        $text = join($this->newLineChar, $this->lines);

        if ($this->hasEndingNewLine && $this->lines)
            $text .= $this->newLineChar;

        return $text;
    }

    /**
     * @param string $line
     *
     * @return self
     */
    function addLine($line = "") {
        return $this->addLines($this->text($line . $this->newLineChar));
    }

    /**
     * @param self $add
     *
     * @return self
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
        return $this->appendLines($this->text($string));
    }

    /**
     * @param self $append
     *
     * @return self
     */
    function appendLines(self $append) {
        $space = str_repeat(' ', $this->width());

        foreach ($append->lines as $k => $line)
            if ($k === 0 && $this->lines)
                $this->lines[count($this->lines) - 1] .= $line;
            else
                $this->lines[] = $space . $line;

        return $this;
    }

    function count() { return count($this->lines); }

    /**
     * @param int $times
     *
     * @return self
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
     * @return self
     */
    function prepend($string) {
        return $this->prependLines($this->text($string));
    }

    function prependLine($line = "") {
        return $this->addLines($this->swapLines($this->text($line . $this->newLineChar)));
    }

    function prependLines(self $lines) {
        return $this->appendLines($this->swapLines($lines));
    }

    /**
     * @param $value
     *
     * @return self
     */
    function setHasEndingNewline($value) {
        $this->hasEndingNewLine = (bool)$value;

        return $this;
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

    private function text($text) {
        return new self($text, $this->newLineChar);
    }
}
