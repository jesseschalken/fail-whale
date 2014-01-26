<?php

namespace ErrorHandler;

final class JSONUnparse implements ValueVisitor {
    /**
     * @param Value $v
     *
     * @return string
     */
    static function toJSON(Value $v) {
        $self               = new self;
        $self->root['root'] = $v->acceptVisitor($self);

        return JSON::stringify($self->root);
    }

    private $root = array(
        'root'    => null,
        'arrays'  => array(),
        'objects' => array(),
    );

    private function __construct() { }

    function visitObject(ValueObject $object) {
        $json =& $this->root['objects'][$object->id()];

        if ($json === null) {
            $json = array(
                'class'      => $object->className(),
                'hash'       => $object->getHash(),
                'properties' => array(),
            );

            foreach ($object->properties() as $p) {
                $json['properties'][] = array(
                    'name'      => $p->name(),
                    'value'     => $p->value()->acceptVisitor($this),
                    'class'     => $p->className(),
                    'access'    => $p->access(),
                    'isDefault' => $p->isDefault(),
                );
            }
        }

        return array('object', $object->id());
    }

    function visitArray(ValueArray $array) {
        $json =& $this->root['arrays'][$array->id()];

        if ($json === null) {
            $json = array(
                'isAssociative' => $array->isAssociative(),
                'entries'       => array(),
            );

            foreach ($array->entries() as $entry) {
                $json['entries'][] = array(
                    $entry->key()->acceptVisitor($this),
                    $entry->value()->acceptVisitor($this),
                );
            }
        }

        return array('array', $array->id());
    }

    function visitException(ValueException $exception) {
        $result = array(
            'class'    => $exception->className(),
            'code'     => $exception->code(),
            'message'  => $exception->message(),
            'previous' => $exception->previous() === null ? null : $this->visitException($exception->previous()),
            'location' => $this->locationToJson($exception->location()),
            'stack'    => array(),
            'locals'   => array(),
            'globals'  => $this->globalsToJson($exception->globals()),
        );

        if ($exception->locals() === null) {
            $result['locals'] = null;
        } else {
            foreach ($exception->locals() as $var) {
                $result['locals'][] = array(
                    'name'  => $var->name(),
                    'value' => $var->value()->acceptVisitor($this),
                );
            }
        }

        foreach ($exception->stack() as $frame) {
            $object = $frame->getObject();
            $json   = array(
                'function' => $frame->getFunction(),
                'class'    => $frame->getClass(),
                'isStatic' => $frame->getIsStatic(),
                'location' => $this->locationToJson($frame->getLocation()),
                'object'   => $object === null ? null : $this->visitObject($object),
                'args'     => array(),
            );

            $args = $frame->getArgs();

            if ($args === null) {
                $json['args'] = null;
            } else {
                foreach ($args as $arg) {
                    $json['args'][] = $arg->acceptVisitor($this);
                }
            }

            $result['stack'][] = $json;
        }

        return array('exception', $result);
    }

    function visitString($string) { return $string; }

    function visitInt($int) { return $int; }

    function visitNull() { return null; }

    function visitUnknown() { return array('unknown'); }

    function visitFloat($float) {
        if ($float === INF)
            $json = '+inf';
        else if ($float === -INF)
            $json = '-inf';
        else if (is_nan($float))
            $json = 'nan';
        else
            $json = $float;

        return array('float', $json);
    }

    function visitResource(ValueResource $r) {
        $json = array(
            'type' => $r->resourceType(),
            'id'   => $r->resourceID(),
        );

        return array('resource', $json);
    }

    function visitBool($bool) { return $bool; }

    private function locationToJson(ValueExceptionCodeLocation $location = null) {
        if ($location === null)
            return null;

        return array(
            'file'       => $location->file(),
            'line'       => $location->line(),
            'sourceCode' => $location->sourceCode(),
        );
    }

    private function globalsToJson(ValueExceptionGlobalState $globals = null) {
        if ($globals === null)
            return null;

        $json = array(
            'staticProperties' => array(),
            'staticVariables'  => array(),
            'globalVariables'  => array(),
        );

        foreach ($globals->getStaticProperties() as $p) {
            $json['staticProperties'][] = array(
                'name'      => $p->name(),
                'value'     => $p->value()->acceptVisitor($this),
                'class'     => $p->className(),
                'access'    => $p->access(),
                'isDefault' => $p->isDefault(),
            );
        }

        foreach ($globals->getStaticVariables() as $v) {
            $json['staticVariables'][] = array(
                'name'     => $v->name(),
                'value'    => $v->value()->acceptVisitor($this),
                'function' => $v->getFunction(),
                'class'    => $v->getClass(),
            );
        }

        foreach ($globals->getGlobalVariables() as $v) {
            $json['globalVariables'][] = array(
                'name'  => $v->name(),
                'value' => $v->value()->acceptVisitor($this),
            );
        }

        return $json;
    }
}

abstract class JSONParse {
    protected $root;
    protected $json;

    function __construct($root, $json) {
        $this->root = $root;
        $this->json = $json;
    }
}

class JSONCodeLocation extends JSONParse implements ValueExceptionCodeLocation {
    function line() { return $this->json['line']; }

    function file() { return $this->json['file']; }

    function sourceCode() { return $this->json['sourceCode']; }
}

class JSONException extends JSONParse implements ValueException {
    function className() { return $this->json[1]['class']; }

    function code() { return $this->json[1]['code']; }

    function message() { return $this->json[1]['message']; }

    function previous() {
        $previous = $this->json[1]['previous'];

        return $previous === null ? null : new self($this->root, $previous);
    }

    function location() { return new JSONCodeLocation($this->root, $this->json[1]['location']); }

    function globals() {
        $globals = $this->json[1]['globals'];

        return $globals === null ? null : new JSONGlobals($this->root, $globals);
    }

    function locals() {
        $locals = $this->json[1]['locals'];
        $root   = $this->root;

        if ($locals === null)
            return null;

        return array_map(function ($json) use ($root) {
            return new JSONVariable($root, $json);
        }, $locals);
    }

    function stack() {
        $root = $this->root;

        return array_map(function ($json) use ($root) {
            return new JSONStackFrame($root, $json);
        }, $this->json[1]['stack']);
    }
}

class JSONStackFrame extends JSONParse implements ValueExceptionStackFrame {
    function getArgs() {
        $args = $this->json['args'];
        $root = $this->root;

        if ($args === null)
            return null;

        return array_map(function ($json) use ($root) {
            return new JSONValue($root, $json);
        }, $args);
    }

    function getFunction() { return $this->json['function']; }

    function getClass() { return $this->json['class']; }

    function getIsStatic() { return $this->json['isStatic']; }

    function getLocation() {
        $location = $this->json['location'];

        return $location === null ? null : new JSONCodeLocation($this->root, $location);
    }

    function getObject() {
        $object = $this->json['object'];

        return $object === null ? null : new JSONObject($this->root, $object);
    }
}

class JSONGlobals extends JSONParse implements ValueExceptionGlobalState {
    function getStaticProperties() {
        $root = $this->root;

        return array_map(function ($json) use ($root) {
            return new JSONObjectProperty($root, $json);
        }, $this->json['staticProperties']);
    }

    function getStaticVariables() {
        $root = $this->root;

        return array_map(function ($json) use ($root) {
            return new JSONStaticVariable($root, $json);
        }, $this->json['staticVariables']);
    }

    function getGlobalVariables() {
        $root = $this->root;

        return array_map(function ($json) use ($root) {
            return new JSONVariable($root, $json);
        }, $this->json['globalVariables']);
    }
}

class JSONVariable extends JSONParse implements ValueVariable {
    function name() { return $this->json['name']; }

    function value() { return new JSONValue($this->root, $this->json['value']); }
}

class JSONStaticVariable extends JSONParse implements ValueVariableStatic {
    function name() { return $this->json['name']; }

    function value() { return new JSONValue($this->root, $this->json['value']); }

    function getFunction() { return $this->json['function']; }

    function getClass() { return $this->json['class']; }
}

class JSONResource extends JSONParse implements ValueResource {
    function resourceType() { return $this->json['type']; }

    function resourceID() { return $this->json['id']; }
}

class JSONObject extends JSONParse implements ValueObject {
    function className() {
        $json = $this->root['objects'][$this->json[1]];

        return $json['class'];
    }

    function properties() {
        $result = array();

        $json = $this->root['objects'][$this->json[1]];
        foreach ($json['properties'] as $property) {
            $result[] = new JSONObjectProperty($this->root, $property);
        }

        return $result;
    }

    function getHash() {
        $json = $this->root['objects'][$this->json[1]];

        return $json['hash'];
    }

    function id() { return $this->json[1]; }
}

class JSONObjectProperty extends JSONParse implements ValueObjectProperty {
    function name() { return $this->json['name']; }

    function value() { return new JSONValue($this->root, $this->json['value']); }

    function access() { return $this->json['access']; }

    function className() { return $this->json['class']; }

    function isDefault() { return $this->json['isDefault']; }
}

class JSONArray extends JSONParse implements ValueArray {
    function isAssociative() {
        $json = $this->root['arrays'][$this->json[1]];

        return $json['isAssociative'];
    }

    function id() { return $this->json[1]; }

    function entries() {
        $json   = $this->root['arrays'][$this->json[1]];
        $result = array();

        foreach ($json['entries'] as $entry) {
            $result[] = new JSONArrayEntry($this->root, $entry);
        }

        return $result;
    }
}

class JSONArrayEntry extends JSONParse implements ValueArrayEntry {
    function key() { return new JSONValue($this->root, $this->json[0]); }

    function value() { return new JSONValue($this->root, $this->json[1]); }
}

class JSONValue extends JSONParse implements Value {
    static function parse($json) {
        $root = JSON::parse($json);

        return new self($root, $root['root']);
    }

    static function fromValue(Value $v) {
        return JSONValue::parse(JSONUnparse::toJSON($v));
    }

    function acceptVisitor(ValueVisitor $visitor) {
        $json = $this->json;

        if (is_float($json)) {
            return $visitor->visitFloat($json);
        } else if (is_int($json)) {
            return $visitor->visitInt($json);
        } else if (is_bool($json)) {
            return $visitor->visitBool($json);
        } else if (is_null($json)) {
            return $visitor->visitNull();
        } else if (is_string($json)) {
            return $visitor->visitString($json);
        } else {
            switch ($json[0]) {
                case 'object':
                    return $visitor->visitObject(new JSONObject($this->root, $json));
                case '-inf':
                case '+inf':
                case 'nan':
                case 'float':
                    return $visitor->visitFloat($this->parseFloat($json[1]));
                case 'array':
                    return $visitor->visitArray(new JSONArray($this->root, $json));
                case 'exception':
                    return $visitor->visitException(new JSONException($this->root, $json));
                case 'resource':
                    return $visitor->visitResource(new JSONResource($this->root, $json[1]));
                case 'unknown':
                    return $visitor->visitUnknown();
                case 'null':
                    return $visitor->visitNull();
                case 'int':
                    return $visitor->visitInt($json[1]);
                case 'bool':
                    return $visitor->visitBool($json[1]);
                case 'string':
                    return $visitor->visitString($json[1]);
                default:
                    throw new Exception("Unknown type: {$json[0]}");
            }
        }
    }

    private function parseFloat($json) {
        if ($json === '+inf')
            return INF;
        else if ($json === '-inf')
            return -INF;
        else if ($json === 'nan')
            return NAN;
        else
            return (float)$json;
    }
}

final class JSON {
    /**
     * @param mixed $value
     *
     * @return string
     */
    static function stringify($value) {
        $flags = 0;

        if (defined('JSON_PRETTY_PRINT')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $result = json_encode(self::prepare($value), $flags);

        self::checkError();

        return $result;
    }

    /**
     * @param string $json
     *
     * @return mixed
     */
    static function parse($json) {
        $result = json_decode($json, true);

        self::checkError();

        return self::unprepare($result);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     * @throws \Exception
     */
    private static function prepare($value) {
        if (is_string($value)) {
            return utf8_encode($value);
        }

        if (is_float($value) || is_int($value) || is_null($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            $result = array();

            foreach ($value as $k => $v) {
                $result[self::prepare($k)] = self::prepare($v);
            }

            return $result;
        }

        throw new \Exception("Invalid JSON value");
    }

    private static function checkError() {
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON Error", json_last_error());
        }
    }

    /**
     * @param mixed $result
     *
     * @throws \Exception
     * @return mixed
     */
    private static function unprepare($result) {
        if (is_string($result)) {
            return utf8_decode($result);
        }

        if (is_float($result) || is_int($result) || is_null($result) || is_bool($result)) {
            return $result;
        }

        if (is_array($result)) {
            $result2 = array();

            foreach ($result as $k => $v) {
                $result2[self::unprepare($k)] = self::unprepare($v);
            }

            return $result2;
        }

        throw new \Exception("Invalid JSON value");
    }
}

