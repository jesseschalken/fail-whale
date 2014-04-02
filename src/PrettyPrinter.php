<?php

namespace ErrorHandler;

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
}

class PrettyPrinter {
    private $settings;
    private $arraysRendered = array();
    private $objectsRendered = array();
    /** @var Root */
    private $root;

    function __construct(Root $root, PrettyPrinterSettings $settings = null) {
        $this->settings = $settings ? : new PrettyPrinterSettings;
        $this->root     = $root;
    }

    function renderRoot() {
        return $this->render($this->root->root);
    }

    private function render(ValueImpl $v) {
        switch ($v->type) {
            case Type::STRING:
                return $this->visitString($this->root->strings[$v->string]);
            case Type::ARRAY1:
                return $this->visitArray($v->array);
            case Type::OBJECT:
                return $this->visitObject($v->object);
            case Type::INT:
                return new Text("$v->int");
            case Type::TRUE:
                return new Text('true');
            case Type::FALSE:
                return new Text('false');
            case Type::NULL:
                return new Text('null');
            case Type::POS_INF:
                return $this->visitFloat(INF);
            case Type::NEG_INF:
                return $this->visitFloat(-INF);
            case Type::NAN:
                return $this->visitFloat(NAN);
            case Type::UNKNOWN:
                return new Text('unknown type');
            case Type::FLOAT:
                return $this->visitFloat($v->float);
            case Type::RESOURCE:
                return new Text("{$v->resource->type}");
            case Type::EXCEPTION:
                return $this->visitException($v->exception);
            default:
                return new Text("unknown type $v->type");
        }
    }

    private function visitString(String1 $string) {
        $result = $this->renderString($string->bytes);

        if ($string->bytesMissing != 0)
            $result->append(" $string->bytesMissing more bytes...");

        return $result;
    }

    private function visitArray($id) {
        $array    = $this->root->arrays[$id];
        $rendered =& $this->arraysRendered[$id];

        if ($rendered)
            return new Text('*recursion*');

        $rendered = true;

        if (!($array->entries) && $array->entriesMissing == 0)
            return new Text("array()");
        else if (!$this->settings->showArrayEntries)
            return new Text("array(...)");

        $rows = array();

        foreach ($array->entries as $keyValuePair) {
            $key   = $this->render($keyValuePair->key);
            $value = $this->render($keyValuePair->value);

            if (count($rows) != count($array->entries) - 1)
                $value->append(',');

            if ($array->isAssociative) {
                $value->prepend(' => ');
                $rows[] = array($key, $value);
            } else {
                $rows[] = array($value);
            }
        }

        $rendered = false;

        $result = Text::table($rows);

        if ($array->entriesMissing != 0)
            $result->addLine("$array->entriesMissing missing entries");

        $result->wrap("array( ", " )");
        return $result;
    }

    private function visitObject($id) {
        $object   = $this->root->objects[$id];
        $rendered =& $this->objectsRendered[$id];

        if ($rendered)
            return new Text('*recursion*');

        $rendered = true;

        if (!$object->properties && $object->propertiesMissing == 0) {
            $result = new Text("new $object->className {}");
        } else if (!$this->settings->showObjectProperties) {
            $result = new Text("new $object->className {...}");
        } else {
            $prefixes = array();

            foreach ($object->properties as $prop)
                $prefixes[] = "$prop->access ";

            $result = $this->renderVariables($object->properties, '', $object->propertiesMissing, $prefixes);
            $result->indent();
            $result->indent();
            $result->wrapLines("new $object->className {", "}");
        }

        $rendered = false;

        return $result;
    }

    private function visitFloat($float) {
        $int = (int)$float;

        return new Text("$int" === "$float" ? "$float.0" : "$float");
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
                $t = new Text('not available');
            }

            $t->indent();
            $text->addLine("global variables:");
            $text->addLines($t);
            $text->addLine();
        }

        return $text;
    }

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
     * @param Variable[] $variables
     * @param string $noneText
     * @param int $missing
     * @param string[] $prefixes
     *
     * @return Text
     */
    private function renderVariables(array $variables, $noneText, $missing, array $prefixes) {
        if (!$variables && $missing == 0)
            return new Text($noneText);

        $rows = array();

        foreach ($variables as $k => $variable) {
            $prefix = new Text($prefixes[$k]);
            $prefix->appendLines($this->renderVariable($variable->name));
            $value = $this->render($variable->value);
            $value->wrap(' = ', ';');
            $rows[] = array($prefix, $value,);
        }

        $result = Text::table($rows);

        if ($missing != 0)
            $result->addLine("$missing missing");

        return $result;
    }

    private function renderException(ExceptionImpl $e) {
        $text = new Text("$e->className $e->code in {$e->location->file}:{$e->location->line}");

        $message = new Text($e->message);
        $message->indent();
        $message->indent();
        $message->wrapLines();
        $text->addLines($message);

        if ($this->settings->showExceptionSourceCode) {
            if (!$e->location->source)
                $source = new Text('not available');
            else
                $source = $this->renderSourceCode($e->location->source, $e->location->line);

            $source->indent();
            $source->wrapLines("source code:");
            $text->addLines($source);
        }

        if ($this->settings->showExceptionLocalVariables) {
            if (!is_array($e->locals)) {
                $locals = new Text('not available');
            } else {
                $prefixes = array_fill(0, count($e->locals), '');
                $locals   = $this->renderVariables($e->locals, 'none', $e->localsMissing, $prefixes);
            }

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

        $previous = $e->previous ? $this->renderException($e->previous) : new Text('none');
        $previous->indent();
        $previous->indent();
        $previous->wrapLines("previous exception:");
        $text->addLines($previous);

        return $text;
    }

    private function renderVariable($name) {
        if (preg_match("/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name))
            return new Text("$$name");

        $result = $this->renderString($name);
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
            $rows[] = array(new Text($codeLine == $line ? "> " : ''),
                            new Text("$codeLine "),
                            new Text($codeText));
        }

        return Text::table($rows);
    }

    private function renderExceptionStack(ExceptionImpl $exception) {
        $text = new Text;
        $i    = 1;

        foreach ($exception->stack as $frame) {
            $location = $frame->location;
            $location = $location
                ? "$location->file:$location->line"
                : '[internal function]';
            $text->addLine("#$i {$location}");
            $call = $this->renderExceptionStackFrame($frame);
            $call->append(';');
            $call->indent();
            $call->indent();
            $call->indent();
            $text->addLines($call);
            $text->addLine();
            $i++;
        }

        if ($exception->stackMissing != 0)
            $text->addLine("$exception->stackMissing missing");
        else
            $text->addLine("#$i {main}");

        return $text;
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
            $prefix = new Text;
            $prefix->append($frame->className);
            $prefix->append($frame->isStatic ? '::' : '->');
            return $prefix;
        } else {
            return new Text;
        }
    }

    private function renderExceptionStackFrameArgs(Stack $frame) {
        if (!is_array($frame->args))
            return new Text("( ? )");

        if ($frame->args === array())
            return new Text("()");

        /** @var Text[] $pretties */
        $pretties    = array();
        $isMultiLine = false;

        foreach ($frame->args as $arg) {
            $pretty      = $this->render($arg);
            $isMultiLine = $isMultiLine || $pretty->count() > 1;
            $pretties[]  = $pretty;
        }

        if ($frame->argsMissing != 0)
            $pretties[] = new Text("$frame->argsMissing more...");

        $result = new Text;

        foreach ($pretties as $k => $pretty) {
            if ($isMultiLine)
                $result->addLines($pretty);
            else
                $result->appendLines($pretty);

            if ($k != count($pretties) - 1)
                $result->append(', ');
        }

        $result->wrap("( ", " )");
        return $result;
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

    function width() {
        return $this->lines ? strlen($this->lines[count($this->lines) - 1]) : 0;
    }

    function padWidth($width) {
        $this->append(str_repeat(' ', $width - $this->width()));
    }

    function appendLines(self $append) {
        $space = str_repeat(' ', $this->width());

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
        $this->appendLines(new self($string));
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

    function addLinesBefore(self $addBefore) {
        $this->addLines($this->swapLines($addBefore));
    }

    function swapLines(self $other) {
        $clone       = clone $this;
        $this->lines = $other->lines;

        return $clone;
    }

    function count() { return count($this->lines); }

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
        $this->prependLines(new self($string));
    }

    function prependLines(self $lines) {
        $this->appendLines($this->swapLines($lines));
    }

    function wrapLines($prepend = '', $append = '') {
        $this->prependLine($prepend);
        $this->addLine($append);
    }

    function prependLine($line = "") {
        $this->addLines($this->swapLines(new self("$line\n")));
    }

    function addLine($line = "") {
        $this->addLines(new self("$line\n"));
    }
}
