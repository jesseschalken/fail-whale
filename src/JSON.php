<?php

namespace ErrorHandler;

class JSONSerialize implements ValueVisitor {
    private $json = array(
        'arrays'  => array(),
        'objects' => array(),
        'strings' => array(),
    );

    function result() { return $this->json; }

    private function serializeObject(ValueObject $object = null) {
        if (!$object instanceof ValueObject)
            return null;

        $json =& $this->json['objects'][$object->id()];

        if ($json === null) {
            $json = array(
                'class'         => $object->className(),
                'properties'    => array(),
                'numProperties' => $object->numProperties(),
            );

            foreach ($object->properties() as $p)
                $json['properties'][] = $this->serializeObjectProperty($p);
        }

        return $object->id();
    }

    function visitObject(ValueObject $object) {
        return array('object', $this->serializeObject($object));
    }

    private function serializeArray(ValueArray $array) {
        $json =& $this->json['arrays'][$array->id()];

        if ($json === null) {
            $json = array(
                'isAssociative' => $array->isAssociative(),
                'entries'       => array(),
                'numEntries'    => $array->numEntries(),
            );

            foreach ($array->entries() as $entry)
                $json['entries'][] = array(
                    $entry->key()->acceptVisitor($this),
                    $entry->value()->acceptVisitor($this),
                );
        }

        return $array->id();
    }

    function visitArray(ValueArray $array) {
        return array('array', $this->serializeArray($array));
    }

    private function serializeException(ValueException $exception = null) {
        if (!$exception instanceof ValueException)
            return null;

        return array(
            'class'          => $exception->className(),
            'code'           => $exception->code(),
            'message'        => $exception->message(),
            'previous'       => $this->serializeException($exception->previous()),
            'location'       => $this->serializeCodeLocation($exception->location()),
            'stack'          => $this->serializeStack($exception->stack()),
            'locals'         => $this->serializeLocals($exception->locals()),
            'globals'        => $this->serializeGlobals($exception->globals()),
            'numStackFrames' => $exception->numStackFrames(),
            'numLocals'      => $exception->numLocals(),
        );
    }

    /**
     * @param ValueStackFrame[] $stack
     *
     * @return array
     */
    private function serializeStack(array $stack) {
        $result = array();

        foreach ($stack as $frame)
            $result[] = array(
                'function' => $frame->functionName(),
                'class'    => $frame->className(),
                'isStatic' => $frame->isStatic(),
                'location' => $this->serializeCodeLocation($frame->location()),
                'object'   => $this->serializeObject($frame->object()),
                'args'     => $this->serializeArgs($frame->arguments()),
                'numArgs'  => $frame->numArguments(),
            );

        return $result;
    }

    /**
     * @param ValueImpl[]|null $args
     *
     * @return array|null
     */
    private function serializeArgs(array $args = null) {
        /** @var ValueImpl[] $args */
        if (!is_array($args))
            return null;

        $result = array();

        foreach ($args as $arg)
            $result[] = $arg->acceptVisitor($this);

        return $result;
    }

    /**
     * @param ValueVariable[] $locals
     *
     * @return array|null
     */
    private function serializeLocals(array $locals = null) {
        if (!is_array($locals))
            return null;

        $result = array();

        foreach ($locals as $var)
            $result[] = $this->serializeVariable($var);

        return $result;
    }

    private function serializeGlobals(ValueGlobals $globals = null) {
        if (!$globals instanceof ValueGlobals)
            return null;

        $result = array(
            'staticProperties'    => array(),
            'staticVariables'     => array(),
            'globalVariables'     => array(),
            'numStaticProperties' => $globals->numStaticProperties(),
            'numStaticVariables'  => $globals->numStaticVariables(),
            'numGlobalVariables'  => $globals->numGlobalVariables(),
        );

        foreach ($globals->staticProperties() as $p)
            $result['staticProperties'][] = $this->serializeObjectProperty($p);

        foreach ($globals->staticVariables() as $v)
            $result['staticVariables'][] = array(
                                               'function' => $v->functionName(),
                                               'class'    => $v->className(),
                                           ) + $this->serializeVariable($v);

        foreach ($globals->globalVariables() as $v)
            $result['globalVariables'][] = $this->serializeVariable($v);

        return $result;
    }

    function visitException(ValueException $exception) {
        return array('exception', $this->serializeException($exception));
    }

    function visitString(ValueString $string) {
        $json =& $this->json['strings'][$string->id()];

        if ($json === null) {
            $json = array(
                'string' => $string->string(),
                'length' => $string->length(),
            );
        }

        return array('string-ref', $string->id());
    }

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

    function visitResource(ValueResource $resource) {
        $json = array(
            'type' => $resource->type(),
            'id'   => $resource->id(),
        );

        return array('resource', $json);
    }

    function visitBool($bool) { return $bool; }

    private function serializeCodeLocation(ValueCodeLocation $location = null) {
        return $location instanceof ValueCodeLocation
            ? array(
                'file'       => $location->file(),
                'line'       => $location->line(),
                'sourceCode' => $location->sourceCode(),
            )
            : null;
    }

    private function serializeObjectProperty(ValueObjectProperty $p) {
        return array(
                   'class'     => $p->className(),
                   'access'    => $p->access(),
                   'isDefault' => $p->isDefault(),
               ) + $this->serializeVariable($p);
    }

    private function serializeVariable(ValueVariable $v) {
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
     * @return ValueImpl
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

        return $previous ? new self($this->root, $previous) : null;
    }

    function location() { return new JSONCodeLocation($this->root, $this->json['location']); }

    function globals() {
        $globals = $this->json['globals'];

        return $globals ? new JSONGlobals($this->root, $globals) : null;
    }

    function locals() {
        $locals = $this->json['locals'];

        if (!is_array($locals))
            return null;

        $result = array();

        foreach ($locals as $json)
            $result[] = new JSONVariable($this->root, $json);

        return $result;
    }

    function stack() {
        $result = array();

        foreach ($this->json['stack'] as $json)
            $result[] = new JSONStackFrame($this->root, $json);

        return $result;
    }

    function numStackFrames() { return $this->json['numStackFrames']; }

    function numLocals() { return $this->json['numLocals']; }
}

class JSONStackFrame extends JSONParse implements ValueStackFrame {
    function arguments() {
        $args = $this->json['args'];

        if (!is_array($args))
            return null;

        $result = array();

        foreach ($args as $json)
            $result[] = new JSONValue($this->root, $json);

        return $result;
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

        return $object ? new JSONObject($this->root, $object) : null;
    }

    function numArguments() { return $this->json['numArgs']; }
}

class JSONGlobals extends JSONParse implements ValueGlobals {
    function staticProperties() {
        $result = array();

        foreach ($this->json['staticProperties'] as $json)
            $result[] = new JSONObjectProperty($this->root, $json);

        return $result;
    }

    function staticVariables() {
        $result = array();

        foreach ($this->json['staticVariables'] as $json)
            $result[] = new JSONStaticVariable($this->root, $json);

        return $result;
    }

    function globalVariables() {
        $result = array();

        foreach ($this->json['globalVariables'] as $json)
            $result[] = new JSONVariable($this->root, $json);

        return $result;
    }

    function numStaticProperties() { return $this->json['numStaticProperties']; }

    function numStaticVariables() { return $this->json['numStaticVariables']; }

    function numGlobalVariables() { return $this->json['numGlobalVariables']; }
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
        $result = array();

        foreach ($this->json['properties'] as $property)
            $result[] = new JSONObjectProperty($this->root, $property);

        return $result;
    }

    function hash() { return $this->json['hash']; }

    function id() { return $this->id; }

    function numProperties() { return $this->json['numProperties']; }
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
        $result = array();

        foreach ($this->json['entries'] as $entry)
            $result[] = new JSONArrayEntry($this->root, $entry);

        return $result;
    }

    function numEntries() { return $this->json['numEntries']; }
}

class JSONArrayEntry extends JSONParse implements ValueArrayEntry {
    function key() { return new JSONValue($this->root, $this->json[0]); }

    function value() { return new JSONValue($this->root, $this->json[1]); }
}

class JSONValue extends JSONParse implements ValueImpl {
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
                case 'string-ref':
                    return $visitor->visitString(new JSONString($this->root, $json[1]));
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

class JSONString extends JSONParse implements ValueString {
    private $id;

    function __construct($root, $json) {
        $this->id = $json;
        $json     = $root['strings'][$json];
        parent::__construct($root, $json);
    }

    function id() { return $this->id; }

    function string() { return $this->json['string']; }

    function length() { return $this->json['length']; }
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

