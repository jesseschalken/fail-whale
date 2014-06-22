<?php

namespace FailWhale;

final class PrettyPrinterSettings {
    public $escapeTabsInStrings = false;
    public $showExceptionGlobalVariables = true;
    public $showExceptionLocalVariables = true;
    public $showExceptionStackTrace = true;
    public $splitMultiLineStrings = true;
    public $showExceptionSourceCode = true;
    public $showObjectProperties = true;
    public $showArrayEntries = true;
    public $showStringContents = true;
    public $longStringThreshold = 1000;
    public $maxStringLength = 1000;
    public $useShortArraySyntax = false;
    public $alignText = false;
    public $alignVariables = true;
    public $alignArrayEntries = true;
    public $indentStackTraceFunctions = true;
}

class PrettyPrinter {
    private $settings;
    private $arraysRendered = array();
    private $objectsRendered = array();
    private $stringsRendered = array();
    /** @var Root */
    private $root;

    function __construct(Root $root, PrettyPrinterSettings $settings = null) {
        $this->settings  = $settings ? : new PrettyPrinterSettings;
        $this->root      = $root;
        $this->refCounts = new RefCounts($root);
    }

    function render() {
        return $this->renderValue($this->root->root);
    }

    private function text($text = '') {
        return new Text($text, $this->settings->alignText);
    }

    private function table(array $rows, $alignColumns) {
        return Text::table($rows, $this->settings->alignText, $alignColumns);
    }

    private function renderValue(ValueImpl $v) {
        switch ($v->type) {
            case Type::STRING:
                return $this->visitString($v->string);
            case Type::ARRAY1:
                return $this->visitArray($v->array);
            case Type::OBJECT:
                return $this->visitObject($v->object);
            case Type::INT:
                return $this->text("$v->int");
            case Type::TRUE:
                return $this->text('true');
            case Type::FALSE:
                return $this->text('false');
            case Type::NULL:
                return $this->text('null');
            case Type::POS_INF:
                return $this->visitFloat(INF);
            case Type::NEG_INF:
                return $this->visitFloat(-INF);
            case Type::NAN:
                return $this->visitFloat(NAN);
            case Type::UNKNOWN:
                return $this->text('unknown type');
            case Type::FLOAT:
                return $this->visitFloat($v->float);
            case Type::RESOURCE:
                return $this->text("{$v->resource->type}");
            case Type::EXCEPTION:
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

    private function renderArrayBody(Array1 $array) {
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

        $rows = array();

        foreach ($array->entries as $keyValuePair) {
            $key   = $this->renderValue($keyValuePair->key);
            $value = $this->renderValue($keyValuePair->value);

            if (count($rows) != count($array->entries) - 1 ||
                $array->entriesMissing != 0 ||
                !$this->settings->alignText
            ) {
                $value->append(',');
            }

            if ($array->isAssociative) {
                $rows[] = array($key, $this->text(' => '), $value);
            } else {
                $rows[] = array($value);
            }
        }

        $result = $this->table($rows, $this->settings->alignArrayEntries);

        if ($array->entriesMissing != 0)
            $result->addLine("$array->entriesMissing more...");

        if ($this->settings->alignText) {
            $result->wrap("$start ", " $end");
        } else {
            $result->indent();
            $result->indent();
            $result->wrapLines($start, $end);
        }

        return $result;
    }

    private function renderObjectBody(Object1 $object) {
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
        $int = (int)$float;

        return $this->text("$int" === "$float" ? "$float.0" : "$float");
    }

    private function visitException(ExceptionImpl $exception) {
        $text = $this->renderException($exception);

        if ($this->settings->showExceptionGlobalVariables) {
            $globals = $exception->globals;

            if ($globals) {
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
            $text->addLine("global variables:");
            $text->addLines($t);
            $text->addLine();
        }

        return $text;
    }

    private function renderString(String1 $string) {
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
            "\n" => '\n',
        );

        $escaped = '';
        $string1 = (string)substr($string->bytes, 0, $this->settings->maxStringLength);
        $length  = strlen($string1);

        for ($i = 0; $i < $length; $i++) {
            $char  = $string1[$i];
            $char2 =& $characterEscapeCache[$char];

            if (!isset($char2)) {
                $ord   = ord($char);
                $char2 = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr('00' . dechex($ord), -2);
            }

            $escaped .= $char2;

            if ($char === "\n" &&
                $i !== $length - 1 &&
                $this->settings->splitMultiLineStrings
            ) {
                $escaped .= "\" .\n\"";
            }
        }

        $result  = $this->text($escaped);
        $skipped = max(0, strlen($string->bytes) - strlen($string1));
        $missing = $string->bytesMissing;
        if ($skipped != 0) {
            $result->wrap('"', "...");
        } else if ($missing != 0) {
            $result->wrap('"', "\" $missing more bytes...");
        } else {
            $result->wrap('"', '"');
        }

        if ($result->count() > 1 && !$this->settings->alignText) {
            $result->indent();
            $result->indent();
            $result->prependLine();
        }

        return $result;
    }

    /**
     * @param Variable[] $variables
     * @param string $noneText
     * @param int $missing
     * @param string[] $prefixes
     *
     * @return Text
     */
    private function renderVariables(array $variables, $noneText, $missing, array $prefixes) {
        if (!$variables && $missing == 0)
            return $this->text($noneText);

        $rows = array();

        foreach ($variables as $k => $variable) {
            $prefix = $this->text($prefixes[$k]);
            $prefix->appendLines($this->renderVariable($variable->name));
            $value = $this->renderValue($variable->value);
            $value->append(';');
            $rows[] = array($prefix, $this->text(' = '), $value,);
        }

        $result = $this->table($rows, $this->settings->alignVariables);

        if ($missing != 0)
            $result->addLine("$missing more...");

        return $result;
    }

    private function renderException(ExceptionImpl $e) {
        $text = $this->text("$e->className $e->code in {$this->renderLocation($e->location)}");

        $message = $this->text($e->message);
        $message->indent();
        $message->indent();
        $message->wrapLines();
        $text->addLines($message);

        if ($this->settings->showExceptionSourceCode) {
            if (!$e->location->source)
                $source = $this->text('not available');
            else
                $source = $this->renderSourceCode($e->location->source, $e->location->line);

            $source->indent();
            $source->wrapLines("source code:");
            $text->addLines($source);
        }

        if ($this->settings->showExceptionLocalVariables && is_array($e->locals)) {
            $prefixes = array_fill(0, count($e->locals), '');
            $locals   = $this->renderVariables($e->locals, 'none', $e->localsMissing, $prefixes);

            $locals->indent();
            $locals->wrapLines("local variables:");
            $text->addLines($locals);
        }

        if ($this->settings->showExceptionStackTrace) {
            $stack = $this->renderExceptionStack($e);
            $stack->indent();
            $stack->wrapLines("stack trace:");
            $text->addLines($stack);
        }

        if ($e->previous) {
            $previous = $this->renderException($e->previous);
            $previous->indent();
            $previous->indent();
            $previous->wrapLines("previous exception:");
            $text->addLines($previous);
        }

        return $text;
    }

    private function renderVariable($name) {
        if (preg_match("/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name))
            return $this->text("$$name");

        $string               = new String1;
        $string->bytes        = $name;
        $string->bytesMissing = 0;

        $result = $this->renderString($string);

        if ($result->count() > 1 && !$this->settings->alignText) {
            $result->indent();
            $result->indent();
            $result->wrapLines('${', '}');
        } else {
            $result->wrap('${', '}');
        }

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

        return $this->table($rows, true);
    }

    private function renderExceptionStack(ExceptionImpl $exception) {
        $rows = array();
        $i    = 1;

        foreach ($exception->stack as $frame) {
            $location = $this->renderLocation($frame->location);
            $call     = $this->renderExceptionStackFrame($frame);

            if ($this->settings->indentStackTraceFunctions) {
                $call->append(';');
                $call->indent();
                $call->indent();
                $call->indent();
                $call->wrapLines($location);
                $rows[] = array($this->text("#$i "), $call);
            } else {
                $rows[] = array($this->text("#$i "), $this->text("$location "), $call);
            }
            $i++;
        }

        if ($exception->stackMissing == 0)
            $rows[] = array($this->text("#$i "), $this->text("{main}"));

        $result = $this->table($rows, true);

        if ($exception->stackMissing != 0)
            $result->addLine("$exception->stackMissing more...");

        return $result;
    }

    private function renderExceptionStackFrame(Stack $frame) {
        $result = $this->renderExceptionStackFramePrefix($frame);
        $result->append($frame->functionName);
        $result->appendLines($this->renderExceptionStackFrameArgs($frame));
        return $result;
    }

    private function renderExceptionStackFramePrefix(Stack $frame) {
        if ($frame->object) {
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

    private function renderExceptionStackFrameArgs(Stack $frame) {
        if (!is_array($frame->args))
            return $this->text("( ? )");

        if ($frame->args === array())
            return $this->text("()");

        /** @var Text[] $pretties */
        $pretties    = array();
        $isMultiLine = false;

        foreach ($frame->args as $arg) {
            /** @var FunctionArg $arg */
            $pretty = $this->renderValue($arg->value);
            if ($arg->name) {
                $pretty->prepend(' = ');
                $pretty->prependLines($this->renderVariable($arg->name));
                if ($arg->isReference)
                    $pretty->prepend('&');
                if ($arg->typeHint !== null)
                    $pretty->prepend("$arg->typeHint ");
            }
            $isMultiLine = $isMultiLine || $pretty->count() > 1;
            $pretties[]  = $pretty;
        }

        if ($frame->argsMissing != 0)
            $pretties[] = $this->text("$frame->argsMissing more...");

        $result = $this->text();

        foreach ($pretties as $k => $pretty) {
            if ($isMultiLine)
                $result->addLines($pretty);
            else
                $result->appendLines($pretty);

            if ($k != count($pretties) - 1)
                $result->append(', ');
        }

        if (!$this->settings->alignText && $isMultiLine) {
            $result->indent();
            $result->indent();
            $result->wrapLines("(", ")");
        } else {
            $result->wrap("( ", " )");
        }

        return $result;
    }

    private function renderLocation(Location $location = null) {
        return $location ? "$location->file:$location->line" : '[internal function]';
    }
}

class RefCounts {
    public $strings = array();
    public $arrays = array();
    public $objects = array();
    private $root;

    function __construct(Root $root) {
        $this->root = $root;
        $this->doValue($root->root);
    }

    private function doValue(ValueImpl $value) {
        switch ($value->type) {
            case Type::ARRAY1:
                $this->doArray($value->array);
                break;
            case Type::OBJECT:
                $this->doObject($value->object);
                break;
            case Type::STRING:
                $refCount =& $this->strings[$value->string];
                $refCount++;
                break;
            case Type::EXCEPTION:
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

    private function doException(ExceptionImpl $e) {
        if ($e->locals)
            foreach ($e->locals as $local)
                $this->doValue($local->value);

        if ($e->globals) {
            foreach ($e->globals->staticVariables as $var)
                $this->doValue($var->value);
            foreach ($e->globals->staticProperties as $var)
                $this->doValue($var->value);
            foreach ($e->globals->globalVariables as $var)
                $this->doValue($var->value);
        }

        if ($e->stack) {
            foreach ($e->stack as $stack) {
                if ($stack->object)
                    $this->doObject($stack->object);

                if ($stack->args)
                    foreach ($stack->args as $arg)
                        $this->doValue($arg->value);
            }
        }

        if ($e->previous)
            $this->doException($e->previous);
    }
}

class Text {
    static function table(array $rows, $align, $alignColumns) {
        $columnWidths = array();

        if ($alignColumns) {
            /** @var $cell self */
            foreach (self::flipArray($rows) as $colNo => $column) {
                $width = 0;

                foreach ($column as $cell)
                    $width = max($width, $cell->width());

                $columnWidths[$colNo] = $width;
            }
        }

        $result = new self('', $align);

        foreach ($rows as $cells) {
            $row        = new self('', $align);
            $lastColumn = count($cells) - 1;

            foreach ($cells as $column => $cell) {
                $cell = clone $cell;

                if ($alignColumns && $column !== $lastColumn)
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
        $space = $this->align ? str_repeat(' ', $this->width()) : '';

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
    private $align;

    function __construct($text, $align) {
        $lines = explode("\n", $text);

        if ($lines && $lines[count($lines) - 1] === "")
            array_pop($lines);

        $this->lines = $lines;
        $this->align = $align;
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
        return new self($text, $this->align);
    }
}
