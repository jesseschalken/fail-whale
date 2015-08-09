<?php

namespace FailWhale;

final class PrettyPrinterSettings {
    public $escapeTabsInStrings = false;
    public $escapeNewlineInStrings = false;
    public $showExceptionGlobalVariables = true;
    public $showExceptionLocalVariables = true;
    public $showExceptionStackTrace = true;
    public $showExceptionSourceCode = true;
    public $showExceptionFunctionArguments = true;
    public $showExceptionFunctionObject = true;
    public $showObjectProperties = true;
    public $showArrayEntries = true;
    public $showStringContents = true;
    public $longStringThreshold = INF;
    public $maxStringLength = INF;
    public $useShortArraySyntax = false;
    public $indentStackTraceFunctions = true;
    public $floatPrecision = 14;
}

class PrettyPrinter {
    private $settings;
    private $arraysRendered = array();
    private $objectsRendered = array();
    private $stringsRendered = array();
    /** @var Data\Root */
    private $root;

    function __construct(Data\Root $root, PrettyPrinterSettings $settings = null) {
        $this->settings  = $settings ? : new PrettyPrinterSettings;
        $this->root      = $root;
        $this->refCounts = new RefCounts($root);
    }

    function render() {
        return $this->renderValue($this->root->root);
    }

    private function text($text = '') {
        return Text::fromString($text);
    }

    private function table(array $rows) {
        return Text::table($rows);
    }

    private function renderValue(Data\Value_ $v) {
        switch ($v->type) {
            case Data\Type::STRING:
                return $this->visitString($v->string);
            case Data\Type::ARRAY1:
                return $this->visitArray($v->array);
            case Data\Type::OBJECT:
                return $this->visitObject($v->object);
            case Data\Type::INT:
                return $this->text("$v->int");
            case Data\Type::TRUE:
                return $this->text('true');
            case Data\Type::FALSE:
                return $this->text('false');
            case Data\Type::NULL:
                return $this->text('null');
            case Data\Type::POS_INF:
                return $this->visitFloat(INF);
            case Data\Type::NEG_INF:
                return $this->visitFloat(-INF);
            case Data\Type::NAN:
                return $this->visitFloat(NAN);
            case Data\Type::UNKNOWN:
                return $this->text('unknown type');
            case Data\Type::FLOAT:
                return $this->visitFloat($v->float);
            case Data\Type::RESOURCE:
                return $this->text("{$v->resource->type}");
            case Data\Type::EXCEPTION:
                return $this->visitException($v->exception);
            default:
                return $this->text("unknown type $v->type");
        }
    }

    private function visitString($id) {
        $string   = $this->root->strings[$id];
        $refCount =& $this->refCounts->strings[$id];

        if ($refCount > 1 && strlen($string->bytes) > $this->settings->longStringThreshold) {
            $rendered =& $this->stringsRendered[$id];
            $idString = sprintf("string%03d", $id);
            if ($rendered) {
                return $this->text("*$idString");
            } else {
                $rendered = true;
                $result   = $this->text("&$idString ");
                $result->appendLines($this->renderString($string));
                return $result;
            }
        } else {
            return $this->renderString($string);
        }
    }

    private function visitArray($id) {
        $array    = $this->root->arrays[$id];
        $refCount =& $this->refCounts->arrays[$id];

        if ($refCount > 1 &&
            (count($array->entries) > 0 || $array->entriesMissing > 0) &&
            $this->settings->showArrayEntries
        ) {
            $rendered =& $this->arraysRendered[$id];
            $idString = sprintf("array%03d", $id);
            if ($rendered) {
                return $this->text("*$idString");
            } else {
                $rendered = true;
                $result   = $this->text("&$idString ");
                $result->appendLines($this->renderArrayBody($array));
                return $result;
            }
        } else {
            return $this->renderArrayBody($array);
        }
    }

    private function visitObject($id) {
        $object   = $this->root->objects[$id];
        $refCount =& $this->refCounts->objects[$id];

        if ($refCount > 1 && $this->settings->showObjectProperties) {
            $rendered =& $this->objectsRendered[$id];
            $idString = sprintf("object%03d", $id);
            if ($rendered) {
                return $this->text("*$idString new $object->className");
            } else {
                $rendered = true;
                $result   = $this->text("&$idString ");
                $result->appendLines($this->renderObjectBody($object));
                return $result;
            }
        } else {
            return $this->renderObjectBody($object);
        }
    }

    private function renderArrayBody(Data\Array_ $array) {
        if ($this->settings->useShortArraySyntax) {
            $start = "[";
            $end   = "]";
        } else {
            $start = "array(";
            $end   = ")";
        }

        if (!$this->settings->showArrayEntries)
            return $this->text("array");
        else if (!($array->entries) && $array->entriesMissing == 0)
            return $this->text("$start$end");

        $result = $this->text();
        foreach ($array->entries as $entry)
            $result->addLines($this->renderArrayEntry($entry, $array->isAssociative));

        if ($array->entriesMissing != 0)
            $result->addLine("$array->entriesMissing more...");

        $result->indent();
        $result->indent();
        $result->wrapLines($start, $end);

        return $result;
    }

    /**
     * @param Data\ArrayEntry $entry
     * @param bool $isAssoc
     * @return Text
     */
    private function renderArrayEntry(Data\ArrayEntry $entry, $isAssoc) {
        $value = $this->renderValue($entry->value);
        $value->append(',');

        if ($isAssoc) {
            $key = $this->renderValue($entry->key);
            $key->append(' => ');
            $value->prependLines($key);
        }

        return $value;
    }

    private function renderObjectBody(Data\Object_ $object) {
        if (!$this->settings->showObjectProperties) {
            return $this->text("new $object->className");
        } else if (!$object->properties && $object->propertiesMissing == 0) {
            return $this->text("new $object->className {}");
        } else {
            $prefixes = array();

            foreach ($object->properties as $prop)
                $prefixes[] = "$prop->access ";

            $result = $this->renderVariables($object->properties, '', $object->propertiesMissing, $prefixes);
            $result->indent();
            $result->indent();
            $result->wrapLines("new $object->className {", "}");
            return $result;
        }
    }

    private function visitFloat($float) {
        if (!is_finite($float)) {
            return $this->text("$float");
        } else {
            list($l, $r) = explode('.', number_format($float, $this->settings->floatPrecision, '.', ''));
            $l = ltrim($l, '0') ?: '0';
            $r = rtrim($r, '0') ?: '0';

            return $this->text("$l.$r");
        }
    }

    private function visitException(Data\Exception_ $exception) {
        $text = $this->text();
        foreach ($exception->exceptions as $e)
            $text->addLines($this->renderException($e));

        if ($this->settings->showExceptionGlobalVariables) {
            $globals = $exception->globals;

            if ($globals) {
                static $superGlobals = array(
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

                foreach ($globals->staticProperties as $p) {
                    $variables[] = $p;
                    $prefixes[]  = "$p->access static $p->className::";
                }

                foreach ($globals->staticVariables as $p) {
                    if ($p->className)
                        $prefixes[] = "function $p->className::$p->functionName()::static ";
                    else
                        $prefixes[] = "function $p->functionName()::static ";
                    $variables[] = $p;
                }

                foreach ($globals->globalVariables as $v) {
                    $variables[] = $v;
                    $prefixes[]  = in_array($v->name, $superGlobals) ? '' : 'global ';
                }

                $missing = $globals->staticPropertiesMissing +
                           $globals->staticVariablesMissing +
                           $globals->globalVariablesMissing;

                $t = $this->renderVariables($variables, 'none', $missing, $prefixes);
            } else {
                $t = $this->text('not available');
            }

            $t->indent();
            $text->addLine("globals:");
            $text->addLines($t);
            $text->addLine();
        }

        return $text;
    }

    private function renderString(Data\String_ $string) {
        if (!$this->settings->showStringContents)
            return $this->text("string");

        $characterEscapeCache = array(
            "\\" => '\\\\',
            "\$" => '\$',
            "\r" => '\r',
            "\v" => '\v',
            "\f" => '\f',
            "\"" => '\"',
            "\t" => $this->settings->escapeTabsInStrings ? '\t' : "\t",
            "\n" => $this->settings->escapeNewlineInStrings ? '\n' : "\n",
        );

        $maxLength = $this->settings->maxStringLength;
        $maxLength = $maxLength === INF ? PHP_INT_MAX : $maxLength;

        $escaped = '';
        $string1 = (string)substr($string->bytes, 0, $maxLength);
        $length  = strlen($string1);

        for ($i = 0; $i < $length; $i++) {
            $char  = $string1[$i];
            $char2 =& $characterEscapeCache[$char];

            if (!isset($char2)) {
                $ord   = ord($char);
                $char2 = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr('00' . dechex($ord), -2);
            }

            $escaped .= $char2;
        }

        $result  = new Text(array($escaped));
        $skipped = max(0, strlen($string->bytes) - strlen($string1));
        $missing = $string->bytesMissing;
        if ($skipped != 0) {
            $result->wrap('"', "...");
        } else if ($missing != 0) {
            $result->wrap('"', "\" $missing more bytes...");
        } else {
            $result->wrap('"', '"');
        }

        return $result;
    }

    /**
     * @param Data\Variable[] $variables
     * @param string $noneText
     * @param int $missing
     * @param string[] $prefixes
     *
     * @return Text
     */
    private function renderVariables(array $variables, $noneText, $missing, array $prefixes) {
        if (!$variables && $missing == 0)
            return $this->text($noneText);

        $result = $this->text();

        foreach ($variables as $k => $variable) {
            $text = $this->text($prefixes[$k]);
            $text->appendLines($this->renderVariable($variable->name));
            $text->append(' = ');
            $text->appendLines($this->renderValue($variable->value));
            $text->append(';');
            $result->addLines($text);
        }

        if ($missing != 0)
            $result->addLine("$missing more...");

        return $result;
    }

    private function renderException(Data\ExceptionData $e) {
        $text = $this->text("$e->className $e->code");

        $message = $this->text($e->message);
        $message->indent();
        $message->indent();
        $message->wrapLines();
        $text->addLines($message);

        if ($this->settings->showExceptionStackTrace) {
            $text->addLines($this->renderExceptionStack($e));
        }

        return $text;
    }

    private function renderVariable($name) {
        if (preg_match("/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name))
            return $this->text("$$name");

        $string               = new Data\String_;
        $string->bytes        = $name;
        $string->bytesMissing = 0;

        $result = $this->renderString($string);
        $result->wrap('${', '}');

        return $result;
    }

    /**
     * @param string[] $code
     * @param int $line
     *
     * @return Text
     */
    private function renderSourceCode(array $code, $line) {
        $rows = array();

        foreach ($code as $codeLine => $codeText) {
            $rows[] = array(
                $this->text($codeLine == $line ? "> " : ''),
                $this->text("$codeLine "),
                $this->text($codeText),
            );
        }

        return $this->table($rows);
    }

    private function renderExceptionStack(Data\ExceptionData $exception) {
        $result = $this->text();

        $i = 1;
        foreach ($exception->stack as $frame)
            $result->addLines($this->renderFrame($frame, $i++));

        if ($exception->stackMissing != 0)
            $result->addLine("$exception->stackMissing more...");

        return $result;
    }

    private function renderExceptionStackFrame(Data\Stack $frame) {
        $result = $this->renderExceptionStackFramePrefix($frame);
        $result->append($frame->functionName);
        $result->appendLines($this->renderExceptionStackFrameArgs($frame));
        return $result;
    }

    private function renderExceptionStackFramePrefix(Data\Stack $frame) {
        if ($frame->object && $this->settings->showExceptionFunctionObject) {
            $prefix = $this->visitObject($frame->object);
            $prefix->append('->');

            if ($this->root->objects[$frame->object]->className !== $frame->className)
                $prefix->append("$frame->className::");

            return $prefix;
        } else if ($frame->className) {
            $prefix = $this->text();
            $prefix->append($frame->className);
            $prefix->append($frame->isStatic ? '::' : '->');
            return $prefix;
        } else {
            return $this->text();
        }
    }

    private function renderExceptionStackFrameArgs(Data\Stack $frame) {
        if (!is_array($frame->args))
            return $this->text("( ? )");

        if ($frame->args === array())
            return $this->text("()");

        if (!$this->settings->showExceptionFunctionArguments)
            return $this->text("( ... )");

        /** @var Text[] $pretties */
        $pretties = array();

        foreach ($frame->args as $arg) {
            /** @var Data\FunctionArg $arg */
            $pretty = $this->renderValue($arg->value);
            if ($arg->name) {
                $pretty->prepend(' = ');
                $pretty->prependLines($this->renderVariable($arg->name));
                if ($arg->isReference)
                    $pretty->prepend('&');
                if ($arg->typeHint !== null)
                    $pretty->prepend("$arg->typeHint ");
            }
            $pretties[] = $pretty;
        }

        if ($frame->argsMissing != 0)
            $pretties[] = $this->text("$frame->argsMissing more...");

        $result = $this->text();

        foreach ($pretties as $k => $pretty) {
            $result->appendLines($pretty);

            if ($k != count($pretties) - 1)
                $result->append(', ');
        }

        $result->wrap("( ", " )");

        return $result;
    }

    private function renderLocation(Data\Location $location = null) {
        return $location ? "$location->file:$location->line" : '[internal function]';
    }

    private function renderFrame(Data\Stack $frame, $i) {
        $location = $frame->location;
        $locals   = $frame->locals;
        $prefix   = '#' . str_pad("$i", 2, ' ', STR_PAD_RIGHT) . ' ';

        $text = $this->text($prefix . $this->renderLocation($location));
        $call = $this->renderExceptionStackFrame($frame);

        if ($this->settings->indentStackTraceFunctions) {
            $call->append(';');
            $call->indent();
            $text->addLines($call);
        } else {
            $text->append('  ');
            $text->appendLines($call);
        }

        if ($locals && $this->settings->showExceptionLocalVariables) {
            $prefixes = $locals ? array_fill(0, count($locals), '') : array();
            $locals   = $this->renderVariables($locals, 'none', $frame->localsMissing, $prefixes);
            $locals->indent();
            $text->addLines($locals);
        }

        if ($location && $location->source && $this->settings->showExceptionSourceCode) {
            $source = $this->renderSourceCode($location->source, $location->line);
            $source->indent();
            $text->addLines($source);
            return $text;
        }
        return $text;
    }
}

class RefCounts {
    public $strings = array();
    public $arrays = array();
    public $objects = array();
    private $root;

    function __construct(Data\Root $root) {
        $this->root = $root;
        $this->doValue($root->root);
    }

    private function doValue(Data\Value_ $value) {
        switch ($value->type) {
            case Data\Type::ARRAY1:
                $this->doArray($value->array);
                break;
            case Data\Type::OBJECT:
                $this->doObject($value->object);
                break;
            case Data\Type::STRING:
                $refCount =& $this->strings[$value->string];
                $refCount++;
                break;
            case Data\Type::EXCEPTION:
                $this->doException($value->exception);
        }
    }

    private function doArray($id) {
        $refCount =& $this->arrays[$id];
        $refCount++;

        if ($refCount == 1) {
            $array = $this->root->arrays[$id];

            foreach ($array->entries as $entry) {
                $this->doValue($entry->key);
                $this->doValue($entry->value);
            }
        }
    }

    private function doObject($id) {
        $refCount =& $this->objects[$id];
        $refCount++;

        if ($refCount == 1) {
            $object = $this->root->objects[$id];

            foreach ($object->properties as $property)
                $this->doValue($property->value);
        }
    }

    private function doException(Data\Exception_ $e) {
        if ($e->globals) {
            foreach ($e->globals->staticVariables as $var)
                $this->doValue($var->value);
            foreach ($e->globals->staticProperties as $var)
                $this->doValue($var->value);
            foreach ($e->globals->globalVariables as $var)
                $this->doValue($var->value);
        }

        foreach ($e->exceptions as $e2) {
            if ($e2->stack) {
                foreach ($e2->stack as $stack) {
                    if ($stack->object)
                        $this->doObject($stack->object);

                    if ($stack->args)
                        foreach ($stack->args as $arg)
                            $this->doValue($arg->value);

                    if ($stack->locals)
                        foreach ($stack->locals as $local)
                            $this->doValue($local->value);
                }
            }
        }
    }
}

class Text {
    static function fromString($text) {
        $lines = explode("\n", $text);

        if ($lines && $lines[count($lines) - 1] === "")
            array_pop($lines);

        return new self($lines);
    }

    static function table(array $rows) {
        $columnWidths = array();

        /** @var $cell self */
        foreach (self::flipArray($rows) as $colNo => $column) {
            $width = 0;
            foreach ($column as $cell)
                $width = max($width, $cell->width());
            $columnWidths[$colNo] = $width;
        }

        $result = self::fromString('');

        foreach ($rows as $cells) {
            $row        = self::fromString('');
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

    function width() {
        return $this->lines ? strlen($this->lines[count($this->lines) - 1]) : 0;
    }

    function padWidth($width) {
        $this->append(str_repeat(' ', $width - $this->width()));
    }

    function appendLines(self $append) {
        $space = '';

        foreach ($append->lines as $k => $line)
            if ($k == 0 && $this->lines)
                $this->lines[count($this->lines) - 1] .= $line;
            else
                $this->lines[] = $space . $line;
    }

    function addLines(self $add) {
        foreach ($add->lines as $line)
            $this->lines[] = $line;
    }

    function append($string) {
        $this->appendLines($this->text($string));
    }

    /** @var string[] */
    private $lines;

    /** @param string[] $lines */
    function __construct($lines) {
        $this->lines = $lines;
    }

    function toString() {
        $text = join("\n", $this->lines);

        return $this->lines ? "$text\n" : $text;
    }

    function addLinesBefore(self $addBefore) {
        $this->addLines($this->swapLines($addBefore));
    }

    function swapLines(self $other) {
        $clone       = clone $this;
        $this->lines = $other->lines;

        return $clone;
    }

    function count() {
        return count($this->lines);
    }

    function indent() {
        foreach ($this->lines as $k => $line)
            if ($line !== '')
                $this->lines[$k] = "  $line";
    }

    function wrap($prepend, $append) {
        $this->prepend($prepend);
        $this->append($append);
    }

    function prepend($string) {
        $this->prependLines($this->text($string));
    }

    function prependLines(self $lines) {
        $this->appendLines($this->swapLines($lines));
    }

    function wrapLines($prepend = '', $append = '') {
        $this->prependLine($prepend);
        $this->addLine($append);
    }

    function prependLine($line = "") {
        $this->addLines($this->swapLines($this->text("$line\n")));
    }

    function addLine($line = "") {
        $this->addLines($this->text("$line\n"));
    }

    private function text($text) {
        return self::fromString($text);
    }
}
