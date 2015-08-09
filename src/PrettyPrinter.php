<?php

namespace FailWhale;

final class PrettyPrinterSettings {
    public $escapeTabsInStrings            = false;
    public $escapeNewlineInStrings         = false;
    public $showExceptionGlobalVariables   = true;
    public $showExceptionLocalVariables    = true;
    public $showExceptionStackTrace        = true;
    public $showExceptionSourceCode        = true;
    public $showExceptionFunctionArguments = true;
    public $showExceptionFunctionObject    = true;
    public $showObjectProperties           = true;
    public $showArrayEntries               = true;
    public $showStringContents             = true;
    public $longStringThreshold            = INF;
    public $maxStringLength                = INF;
    public $useShortArraySyntax            = false;
    public $indentStackTraceFunctions      = true;
}

class PrettyPrinter {
    private $settings;
    private $arraysRendered  = array();
    private $objectsRendered = array();
    private $stringsRendered = array();
    /** @var Data\Root */
    private $root;

    function __construct(Data\Root $root, PrettyPrinterSettings $settings = null) {
        $this->settings  = $settings ?: new PrettyPrinterSettings;
        $this->root      = $root;
        $this->refCounts = new RefCounts($root);
    }

    function render() {
        return $this->renderValue($this->root->root, "\n");
    }

    private function renderValue(Data\Value_ $v, $nl) {
        switch ($v->type) {
            case Data\Type::STRING:
                return $this->visitString($v->string);
            case Data\Type::ARRAY1:
                return $this->visitArray($v->array, $nl);
            case Data\Type::OBJECT:
                return $this->visitObject($v->object, $nl);
            case Data\Type::INT:
                return (string)$v->int;
            case Data\Type::TRUE:
                return 'true';
            case Data\Type::FALSE:
                return 'false';
            case Data\Type::NULL:
                return 'null';
            case Data\Type::POS_INF:
                return (string)INF;
            case Data\Type::NEG_INF:
                return (string)-INF;
            case Data\Type::NAN:
                return (string)NAN;
            case Data\Type::UNKNOWN:
                return 'unknown type';
            case Data\Type::FLOAT:
                return (string)$v->float;
            case Data\Type::RESOURCE:
                return (string)$v->resource->type;
            case Data\Type::EXCEPTION:
                return $this->visitException($v->exception, $nl);
            default:
                return "unknown type $v->type";
        }
    }

    private function visitString($id) {
        $string   = $this->root->strings[$id];
        $refCount =& $this->refCounts->strings[$id];

        if ($refCount > 1 && strlen($string->bytes) > $this->settings->longStringThreshold) {
            $rendered =& $this->stringsRendered[$id];
            $idString = sprintf("string%03d", $id);
            if ($rendered) {
                return "*$idString";
            } else {
                $rendered = true;
                return "&$idString " . $this->renderString($string);
            }
        } else {
            return $this->renderString($string);
        }
    }

    private function visitArray($id, $nl) {
        $array    = $this->root->arrays[$id];
        $refCount =& $this->refCounts->arrays[$id];

        if ($refCount > 1 &&
            (count($array->entries) > 0 || $array->entriesMissing > 0) &&
            $this->settings->showArrayEntries
        ) {
            $rendered =& $this->arraysRendered[$id];
            $idString = sprintf("array%03d", $id);
            if ($rendered) {
                return "*$idString";
            } else {
                $rendered = true;
                return "&$idString " . $this->renderArrayBody($array, $nl);
            }
        } else {
            return $this->renderArrayBody($array, $nl);
        }
    }

    private function visitObject($id, $nl) {
        $object   = $this->root->objects[$id];
        $refCount =& $this->refCounts->objects[$id];

        if ($refCount > 1 && $this->settings->showObjectProperties) {
            $rendered =& $this->objectsRendered[$id];
            $idString = sprintf("object%03d", $id);
            if ($rendered) {
                return "*$idString new $object->className";
            } else {
                $rendered = true;
                return "&$idString " . $this->renderObjectBody($object, $nl);
            }
        } else {
            return $this->renderObjectBody($object, $nl);
        }
    }

    private function renderArrayBody(Data\Array_ $array, $nl) {
        $nl2 = "$nl    ";
        if ($this->settings->useShortArraySyntax) {
            $start = "[";
            $end   = "]";
        } else {
            $start = "array(";
            $end   = ")";
        }

        if (!$this->settings->showArrayEntries)
            return "array";
        else if (!($array->entries) && $array->entriesMissing == 0)
            return "$start$end";

        $result = "$start";
        foreach ($array->entries as $entry) {
            $key   = $this->renderValue($entry->key, $nl2);
            $value = $this->renderValue($entry->value, $nl2);

            if ($array->isAssociative) {
                $result .= "$nl2$key => $value,";
            } else {
                $result .= "$nl2$value,";
            }
        }

        if ($array->entriesMissing != 0)
            $result .= "$nl2$array->entriesMissing more...";

        $result .= "$nl$end";

        return $result;
    }

    private function renderObjectBody(Data\Object_ $object, $nl) {
        if (!$this->settings->showObjectProperties) {
            return "new $object->className";
        } else {
            $nl2   = "$nl    ";
            $start = "new $object->className {";
            $end   = "}";

            if (!$object->properties && $object->propertiesMissing == 0)
                return "$start$end";

            $text = $start;
            foreach ($object->properties as $prop) {
                $text .= "$nl2$prop->access " . $this->renderVariable($prop, $nl2);
            }
            if ($object->propertiesMissing) {
                $text .= "$nl2$object->propertiesMissing more...";
            }
            $text .= $nl . $end;

            return $text;
        }
    }

    private function visitException(Data\Exception_ $exception, $nl) {
        $text = '';

        foreach ($exception->exceptions as $e) {
            $text .= $this->renderException($e, $nl);
        }

        if ($this->settings->showExceptionGlobalVariables) {
            $text .= $nl . "globals:";

            if ($exception->globals) {
                $text .= $this->renderGlobals($exception->globals, "$nl    ");
            } else {
                $text .= "$nl    not available";
            }
        }

        return $text;
    }

    private function renderGlobals(Data\Globals $globals, $nl) {
        $text = '';

        foreach ($globals->staticProperties as $p) {
            $text .= $nl . "$p->access static $p->className::" . $this->renderVariable($p, $nl);
        }

        foreach ($globals->staticVariables as $p) {
            if ($p->className) {
                $text .= $nl . "function $p->className::$p->functionName()::static " . $this->renderVariable($p, $nl);
            } else {
                $text .= $nl . "function $p->functionName()::static " . $this->renderVariable($p, $nl);
            }
        }

        foreach ($globals->globalVariables as $v) {
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

            if (in_array($v->name, $superGlobals)) {
                $text .= $nl . $this->renderVariable($v, $nl);
            } else {
                $text .= $nl . 'global ' . $this->renderVariable($v, $nl);
            }
        }

        $missing =
            $globals->staticPropertiesMissing +
            $globals->staticVariablesMissing +
            $globals->globalVariablesMissing;

        if ($missing)
            $text .= "$nl$missing more...";

        return $text ?: "{$nl}none";
    }

    private function renderString(Data\String_ $string) {
        if (!$this->settings->showStringContents)
            return "string";

        $max     = $this->settings->maxStringLength;
        $bytes   = $string->bytes;
        $trim    = strlen($bytes) > $max;
        $escaped = $this->escapeString($trim ? substr($bytes, 0, $max) : $bytes);

        if ($trim) {
            return "\"$escaped...";
        } else if ($string->bytesMissing != 0) {
            return "\"$escaped\" $string->bytesMissing more bytes...";
        } else {
            return "\"$escaped\"";
        }
    }

    private function renderVariable(Data\Variable $variable, $nl) {
        return
            $this->renderVariableName($variable->name) . ' = ' .
            $this->renderValue($variable->value, $nl) . ';';
    }

    /**
     * @param string $name
     * @return string
     */
    private function renderVariableName($name) {
        if (preg_match("/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/", $name)) {
            return "$$name";
        } else {
            return "\${\"" . $this->escapeString($name) . "\"}";
        }
    }

    private function renderException(Data\ExceptionData $e, $nl) {
        $text = "$e->className $e->code \"" . $this->escapeString($e->message) . "\"";

        if ($this->settings->showExceptionStackTrace) {
            $i = 1;
            foreach ($e->stack as $frame)
                $text .= $nl . $this->renderStackFrame($frame, $i++, $nl);

            if ($e->stackMissing != 0)
                $text .= $nl . "$e->stackMissing more...";
        }

        return $text;
    }

    /**
     * @param string[] $code
     * @param int $line
     * @param string $nl
     * @return string
     */
    private function renderSourceCode(array $code, $line, $nl) {
        $text = '';

        foreach ($code as $codeLine => $codeText) {
            $p = $codeLine == $line ? '>' : ' ';
            $n = str_pad("$codeLine", 4, ' ', STR_PAD_LEFT);
            $text .= "$nl$p $n $codeText";
        }

        return $text;
    }

    private function renderFunctionCall(Data\Stack $frame, $nl) {
        if ($frame->object && $this->settings->showExceptionFunctionObject) {
            $text = $this->visitObject($frame->object, $nl) . '->';
            if ($this->root->objects[$frame->object]->className !== $frame->className)
                $text .= "$frame->className::";
        } else if ($frame->className) {
            $text = $frame->className . ($frame->isStatic ? '::' : '->');
        } else {
            $text = '';
        }

        $text .= $frame->functionName;

        if (!is_array($frame->args)) {
            $text .= "( ? )";
        } else if (!$frame->args) {
            $text .= "()";
        } else if (!$this->settings->showExceptionFunctionArguments) {
            $text .= "( ... )";
        } else {
            /** @var string[] $args */
            $args = array();

            /** @var Data\FunctionArg $arg */
            foreach ($frame->args as $arg) {
                $prefix = '';
                if ($arg->typeHint !== null)
                    $prefix .= "$arg->typeHint ";
                if ($arg->isReference)
                    $prefix .= '&';
                if ($arg->name)
                    $prefix .= $this->renderVariableName($arg->name) . ' = ';
                $args[] = $prefix . $this->renderValue($arg->value, $nl);
            }

            if ($frame->argsMissing != 0)
                $args[] = "$frame->argsMissing more...";

            $text .= '( ' . join(', ', $args) . ' )';
        }

        return $text;
    }

    private function renderStackFrame(Data\Stack $frame, $i, $nl) {
        $location = $frame->location;
        $locals   = $frame->locals;
        $prefix   = '#' . str_pad("$i", 2, ' ', STR_PAD_RIGHT) . ' ';

        $text = $prefix . ($location ? "$location->file:$location->line" : '[internal function]');

        $nl2 = "$nl        ";
        if ($this->settings->indentStackTraceFunctions) {
            $text .= $nl2 . $this->renderFunctionCall($frame, $nl2) . ';';
        } else {
            $text .= ' ' . $this->renderFunctionCall($frame, $nl);
        }

        if ($locals && $this->settings->showExceptionLocalVariables) {
            foreach ($frame->locals as $var) {
                $text .= $nl2 . $this->renderVariable($var, $nl2);
            }
            if ($frame->localsMissing > 0) {
                $text .= $nl2 . "$frame->localsMissing more...";
            }
        }

        if ($location && $location->source && $this->settings->showExceptionSourceCode) {
            $text .= $this->renderSourceCode($location->source, $location->line, $nl2);
        }

        return $text;
    }

    /**
     * @param string $string1
     * @return string
     */
    private function escapeString($string1) {
        $cache = array(
            "\\" => '\\\\',
            "\$" => '\$',
            "\r" => '\r',
            "\v" => '\v',
            "\f" => '\f',
            "\"" => '\"',
            "\t" => $this->settings->escapeTabsInStrings ? '\t' : "\t",
            "\n" => $this->settings->escapeNewlineInStrings ? '\n' : "\n",
        );

        $string2 = '';
        $length  = strlen($string1);

        for ($i = 0; $i < $length; $i++) {
            $char1 = $string1[$i];
            $char2 =& $cache[$char1];

            if ($char2 === null) {
                $ord = ord($char1);
                if ($ord >= 32 && $ord <= 126) {
                    $char2 = $char1;
                } else {
                    $char2 = '\x' . substr('00' . dechex($ord), -2);
                }
            }

            $string2 .= $char2;
        }
        return $string2;
    }
}

class RefCounts {
    public  $strings = array();
    public  $arrays  = array();
    public  $objects = array();
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

