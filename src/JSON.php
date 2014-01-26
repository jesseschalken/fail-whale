<?php

namespace ErrorHandler;

final class JSONSerialize implements ValueVisitor {
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
            $that = $this;
            $json = array();
            $json = array(
                'class'      => $object->className(),
                'hash'       => $object->hash(),
                'properties' => array_map(
                    function (ValueObjectProperty $p) use ($that) {
                        return array(
                            'name'      => $p->name(),
                            'value'     => $p->value()->acceptVisitor($that),
                            'class'     => $p->className(),
                            'access'    => $p->access(),
                            'isDefault' => $p->isDefault(),
                        );
                    },
                    $object->properties()
                ),
            );
        }

        return array('object', $object->id());
    }

    function visitArray(ValueArray $array) {
        $json =& $this->root['arrays'][$array->id()];

        if ($json === null) {
            $that = $this;
            $json = array();
            $json = array(
                'isAssociative' => $array->isAssociative(),
                'entries'       => array_map(
                    function (ValueArrayEntry $entry) use ($that) {
                        return array(
                            $entry->key()->acceptVisitor($that),
                            $entry->value()->acceptVisitor($that),
                        );
                    },
                    $array->entries()
                ),
            );
        }

        return array('array', $array->id());
    }

    function visitException(ValueException $exception) {
        $that = $this;

        $locals   = $exception->locals();
        $globals  = $exception->globals();
        $previous = $exception->previous();
        $result   = array(
            'class'    => $exception->className(),
            'code'     => $exception->code(),
            'message'  => $exception->message(),
            'previous' => $previous === null ? null : $this->visitException($previous),
            'location' => $this->locationToJson($exception->location()),
            'stack'    => array_map(
                function (ValueStackFrame $frame) use ($that) {
                    $object = $frame->object();
                    $args   = $frame->arguments();

                    return array(
                        'function' => $frame->functionName(),
                        'class'    => $frame->className(),
                        'isStatic' => $frame->isStatic(),
                        'location' => $that->locationToJson($frame->location()),
                        'object'   => $object === null ? null : $that->visitObject($object),
                        'args'     => $args === null
                                ? null
                                : array_map(
                                    function (Value $arg) use ($that) {
                                        return $arg->acceptVisitor($that);
                                    },
                                    $args
                                ),
                    );
                },
                $exception->stack()
            ),
            'locals'   => $locals === null
                    ? null
                    : array_map(
                        function (ValueVariable $var) use ($that) {
                            return array(
                                'name'  => $var->name(),
                                'value' => $var->value()->acceptVisitor($that),
                            );
                        },
                        $locals
                    ),
            'globals'  => $globals === null
                    ? null
                    : array(
                        'staticProperties' => array_map(
                            function (ValueObjectProperty $p) use ($that) {
                                return array(
                                    'name'      => $p->name(),
                                    'value'     => $p->value()->acceptVisitor($that),
                                    'class'     => $p->className(),
                                    'access'    => $p->access(),
                                    'isDefault' => $p->isDefault(),
                                );
                            },
                            $globals->staticProperties()
                        ),
                        'staticVariables'  => array_map(
                            function (ValueStaticVariable $v) use ($that) {
                                return array(
                                    'name'     => $v->name(),
                                    'value'    => $v->value()->acceptVisitor($that),
                                    'function' => $v->functionName(),
                                    'class'    => $v->className(),
                                );
                            },
                            $globals->staticVariables()
                        ),
                        'globalVariables'  => array_map(
                            function (ValueVariable $v) use ($that) {
                                return array(
                                    'name'  => $v->name(),
                                    'value' => $v->value()->acceptVisitor($that),
                                );
                            },
                            $globals->globalVariables()
                        ),
                    ),
        );

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
            'type' => $r->type(),
            'id'   => $r->id(),
        );

        return array('resource', $json);
    }

    function visitBool($bool) { return $bool; }

    private function locationToJson(ValueCodeLocation $location = null) {
        if ($location === null)
            return null;

        return array(
            'file'       => $location->file(),
            'line'       => $location->line(),
            'sourceCode' => $location->sourceCode(),
        );
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

class JSONCodeLocation extends JSONParse implements ValueCodeLocation {
    function line() { return $this->json['line']; }

    function file() { return $this->json['file']; }

    function sourceCode() { return $this->json['sourceCode']; }
}

class JSONException extends JSONParse implements ValueException {
    function className() { return $this->json['class']; }

    function code() { return $this->json['code']; }

    function message() { return $this->json['message']; }

    function previous() {
        $previous = $this->json['previous'];

        return $previous === null ? null : new self($this->root, $previous[1]);
    }

    function location() { return new JSONCodeLocation($this->root, $this->json['location']); }

    function globals() {
        $globals = $this->json['globals'];

        return $globals === null ? null : new JSONGlobals($this->root, $globals);
    }

    function locals() {
        $root   = $this->root;
        $locals = $this->json['locals'];

        if ($locals === null)
            return null;

        return array_map(
            function ($json) use ($root) {
                return new JSONVariable($root, $json);
            },
            $locals
        );
    }

    function stack() {
        $root = $this->root;

        return array_map(
            function ($json) use ($root) {
                return new JSONStackFrame($root, $json);
            },
            $this->json['stack']
        );
    }
}

class JSONStackFrame extends JSONParse implements ValueStackFrame {
    function arguments() {
        $args = $this->json['args'];
        $root = $this->root;

        if ($args === null)
            return null;

        return array_map(function ($json) use ($root) {
            return new JSONValue($root, $json);
        }, $args);
    }

    function functionName() { return $this->json['function']; }

    function className() { return $this->json['class']; }

    function isStatic() { return $this->json['isStatic']; }

    function location() {
        $location = $this->json['location'];

        return $location === null ? null : new JSONCodeLocation($this->root, $location);
    }

    function object() {
        $object = $this->json['object'];

        return $object === null ? null : new JSONObject($this->root, $object[1]);
    }
}

class JSONGlobals extends JSONParse implements ValueGlobals {
    function staticProperties() {
        $root = $this->root;

        return array_map(
            function ($json) use ($root) {
                return new JSONObjectProperty($root, $json);
            },
            $this->json['staticProperties']
        );
    }

    function staticVariables() {
        $root = $this->root;

        return array_map(
            function ($json) use ($root) {
                return new JSONStaticVariable($root, $json);
            },
            $this->json['staticVariables']
        );
    }

    function globalVariables() {
        $root = $this->root;

        return array_map(
            function ($json) use ($root) {
                return new JSONVariable($root, $json);
            },
            $this->json['globalVariables']
        );
    }
}

class JSONVariable extends JSONParse implements ValueVariable {
    function name() { return $this->json['name']; }

    function value() { return new JSONValue($this->root, $this->json['value']); }
}

class JSONStaticVariable extends JSONParse implements ValueStaticVariable {
    function name() { return $this->json['name']; }

    function value() { return new JSONValue($this->root, $this->json['value']); }

    function functionName() { return $this->json['function']; }

    function className() { return $this->json['class']; }
}

class JSONResource extends JSONParse implements ValueResource {
    function type() { return $this->json['type']; }

    function id() { return $this->json['id']; }
}

class JSONObject extends JSONParse implements ValueObject {
    private $id;

    function __construct($root, $json) {
        $this->id = $json;
        $json     = $root['objects'][$json];
        parent::__construct($root, $json);
    }

    function className() { return $this->json['class']; }

    function properties() {
        $result = array();

        foreach ($this->json['properties'] as $property) {
            $result[] = new JSONObjectProperty($this->root, $property);
        }

        return $result;
    }

    function hash() { return $this->json['hash']; }

    function id() { return $this->id; }
}

class JSONObjectProperty extends JSONParse implements ValueObjectProperty {
    function name() { return $this->json['name']; }

    function value() { return new JSONValue($this->root, $this->json['value']); }

    function access() { return $this->json['access']; }

    function className() { return $this->json['class']; }

    function isDefault() { return $this->json['isDefault']; }
}

class JSONArray extends JSONParse implements ValueArray {
    private $id;

    function __construct($root, $json) {
        $this->id = $json;
        $json     = $root['arrays'][$json];
        parent::__construct($root, $json);
    }

    function isAssociative() { return $this->json['isAssociative']; }

    function id() { return $this->id; }

    function entries() {
        $result = array();

        foreach ($this->json['entries'] as $entry) {
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
        return JSONValue::parse(JSONSerialize::toJSON($v));
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
                    return $visitor->visitObject(new JSONObject($this->root, $json[1]));
                case '-inf':
                case '+inf':
                case 'nan':
                case 'float':
                    return $visitor->visitFloat($this->parseFloat($json[1]));
                case 'array':
                    return $visitor->visitArray(new JSONArray($this->root, $json[1]));
                case 'exception':
                    return $visitor->visitException(new JSONException($this->root, $json[1]));
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

        if (is_float($result) ||
            is_int($result) ||
            is_null($result) ||
            is_bool($result)
        ) {
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

