<?php

namespace ErrorHandler;

class JSONSerialize implements ValueVisitor {
    /**
     * @param Value $v
     *
     * @return string
     */
    static function serialize(Value $v) {
        $self = new self;

        $self->root['root'] = $v->acceptVisitor($self);

        return JSON::encode($self->root);
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
                        return $that->serializeObjectProperty($p);
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
            'previous' => $previous instanceof ValueException ? $this->visitException($previous) : null,
            'location' => $this->serializeCodeLocation($exception->location()),
            'stack'    => array_map(
                function (ValueStackFrame $frame) use ($that) {
                    $object = $frame->object();
                    $args   = $frame->arguments();

                    return array(
                        'function' => $frame->functionName(),
                        'class'    => $frame->className(),
                        'isStatic' => $frame->isStatic(),
                        'location' => $that->serializeCodeLocation($frame->location()),
                        'object'   => $object instanceof ValueObject ? $that->visitObject($object) : null,
                        'args'     => is_array($args)
                                ? array_map(
                                    function (Value $arg) use ($that) {
                                        return $arg->acceptVisitor($that);
                                    },
                                    $args
                                )
                                : null,
                    );
                },
                $exception->stack()
            ),
            'locals'   => is_array($locals)
                    ? array_map(
                        function (ValueVariable $var) use ($that) {
                            return $that->serializeVariable($var);
                        },
                        $locals
                    )
                    : null,
            'globals'  => $globals instanceof ValueGlobals
                    ? array(
                        'staticProperties' => array_map(
                            function (ValueObjectProperty $p) use ($that) {
                                return $that->serializeObjectProperty($p);
                            },
                            $globals->staticProperties()
                        ),
                        'staticVariables'  => array_map(
                            function (ValueStaticVariable $v) use ($that) {
                                return array(
                                           'function' => $v->functionName(),
                                           'class'    => $v->className(),
                                       ) + $that->serializeVariable($v);
                            },
                            $globals->staticVariables()
                        ),
                        'globalVariables'  => array_map(
                            function (ValueVariable $v) use ($that) {
                                return $that->serializeVariable($v);
                            },
                            $globals->globalVariables()
                        ),
                    )
                    : null,
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

    function serializeCodeLocation(ValueCodeLocation $location = null) {
        return $location instanceof ValueCodeLocation
            ? array(
                'file'       => $location->file(),
                'line'       => $location->line(),
                'sourceCode' => $location->sourceCode(),
            )
            : null;
    }

    function serializeObjectProperty(ValueObjectProperty $p) {
        return array(
                   'class'     => $p->className(),
                   'access'    => $p->access(),
                   'isDefault' => $p->isDefault(),
               ) + $this->serializeVariable($p);
    }

    function serializeVariable(ValueVariable $v) {
        return array(
            'name'  => $v->name(),
            'value' => $v->value()->acceptVisitor($this),
        );
    }
}

abstract class JSONParse {
    /**
     * @param string $json
     *
     * @return Value
     */
    static function parse($json) {
        $root = JSON::decode($json);

        return new JSONValue($root, $root['root']);
    }

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

        return $previous ? new self($this->root, $previous[1]) : null;
    }

    function location() { return new JSONCodeLocation($this->root, $this->json['location']); }

    function globals() {
        $globals = $this->json['globals'];

        return $globals ? new JSONGlobals($this->root, $globals) : null;
    }

    function locals() {
        $root   = $this->root;
        $locals = $this->json['locals'];

        if (!is_array($locals))
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

        if (!is_array($args))
            return null;

        return array_map(
            function ($json) use ($root) {
                return new JSONValue($root, $json);
            },
            $args
        );
    }

    function functionName() { return $this->json['function']; }

    function className() { return $this->json['class']; }

    function isStatic() { return $this->json['isStatic']; }

    function location() {
        $location = $this->json['location'];

        return $location ? new JSONCodeLocation($this->root, $location) : null;
    }

    function object() {
        $object = $this->json['object'];

        return $object ? new JSONObject($this->root, $object[1]) : null;
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

class JSONStaticVariable extends JSONVariable implements ValueStaticVariable {
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
        $root = $this->root;

        return array_map(
            function ($property) use ($root) {
                return new JSONObjectProperty($root, $property);
            },
            $this->json['properties']
        );
    }

    function hash() { return $this->json['hash']; }

    function id() { return $this->id; }
}

class JSONObjectProperty extends JSONVariable implements ValueObjectProperty {
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
        $root = $this->root;

        return array_map(
            function ($entry) use ($root) {
                return new JSONArrayEntry($root, $entry);
            },
            $this->json['entries']
        );
    }
}

class JSONArrayEntry extends JSONParse implements ValueArrayEntry {
    function key() { return new JSONValue($this->root, $this->json[0]); }

    function value() { return new JSONValue($this->root, $this->json[1]); }
}

class JSONValue extends JSONParse implements Value {
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
                    return $visitor->visitFloat($this->parseFloat($json[0]));
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

/**
 * Provides versions of json_encode and json_decode which work with arbitrary byte strings, not just valid UTF-8 ones.
 */
final class JSON {
    /**
     * @param mixed $value
     *
     * @return string
     */
    static function encode($value) {
        $value = self::translateStrings($value, function ($x) { return utf8_encode($x); });
        $json  = json_encode($value, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);

        self::checkError();

        return $json;
    }

    /**
     * @param string $json
     *
     * @return mixed
     */
    static function decode($json) {
        $value = json_decode($json, true);

        self::checkError();

        $value = self::translateStrings($value, function ($x) { return utf8_decode($x); });

        return $value;
    }

    /**
     * @param mixed    $value
     * @param callable $f
     *
     * @throws \Exception
     * @return mixed
     */
    private static function translateStrings($value, \Closure $f) {
        if (is_string($value)) {
            return $f($value);
        } else if (is_float($value) ||
                   is_int($value) ||
                   is_null($value) ||
                   is_bool($value)
        ) {
            return $value;
        } else if (is_array($value)) {
            $result = array();

            foreach ($value as $k => $v) {
                $k = self::translateStrings($k, $f);
                $v = self::translateStrings($v, $f);

                $result[$k] = $v;
            }

            return $result;
        } else {
            throw new \Exception("Invalid JSON value");
        }
    }

    private static function checkError() {
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON Error", json_last_error());
        }
    }
}

