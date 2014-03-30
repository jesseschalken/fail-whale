<?php

namespace ErrorHandler;

class JSONRoot implements ValueVisitor {
    const ARRAYS  = 'arrays';
    const OBJECTS = 'objects';
    const STRINGS = 'strings';

    private $json = array(
        self::ARRAYS  => array(),
        self::OBJECTS => array(),
        self::STRINGS => array(),
    );

    function result() { return $this->json; }

    private function serializeObject(ValueObject $object = null) {
        if (!$object instanceof ValueObject)
            return null;

        $json =& $this->json[self::OBJECTS][$object->id()];

        if ($json === null) {
            $json = array(
                JSONObject::CLASS_NAME         => $object->className(),
                JSONObject::PROPERTIES         => array(),
                JSONObject::PROPERTIES_MISSING => $object->propertiesMissing(),
            );

            foreach ($object->properties() as $p)
                $json[JSONObject::PROPERTIES][] = $this->serializeObjectProperty($p);
        }

        return $object->id();
    }

    function visitObject(ValueObject $object) {
        return array(
            JSONValue::PROP_TYPE   => JSONValue::TYPE_OBJECT,
            JSONValue::PROP_OBJECT => $this->serializeObject($object),
        );
    }

    private function serializeArray(ValueArray $array) {
        $json =& $this->json[self::ARRAYS][$array->id()];

        if ($json === null) {
            $json = array(
                JSONArray::IS_ASSOCIATIVE  => $array->isAssociative(),
                JSONArray::ENTRIES         => array(),
                JSONArray::ENTRIES_MISSING => $array->entriesMissing(),
            );

            foreach ($array->entries() as $entry)
                $json[JSONArray::ENTRIES][] = array(
                    JSONArrayEntry::KEY   => $entry->key()->acceptVisitor($this),
                    JSONArrayEntry::VALUE => $entry->value()->acceptVisitor($this),
                );
        }

        return $array->id();
    }

    function visitArray(ValueArray $array) {
        return array(
            JSONValue::PROP_TYPE  => JSONValue::TYPE_ARRAY,
            JSONValue::PROP_ARRAY => $this->serializeArray($array),
        );
    }

    private function serializeException(ValueException $exception = null) {
        if (!$exception instanceof ValueException)
            return null;

        return array(
            JSONException::CLASS_NAME     => $exception->className(),
            JSONException::CODE           => $exception->code(),
            JSONException::MESSAGE        => $exception->message(),
            JSONException::PREVIOUS       => $this->serializeException($exception->previous()),
            JSONException::LOCATION       => $this->serializeCodeLocation($exception->location()),
            JSONException::STACK          => $this->serializeStack($exception->stack()),
            JSONException::LOCALS         => $this->serializeLocals($exception->locals()),
            JSONException::GLOBALS        => $this->serializeGlobals($exception->globals()),
            JSONException::STACK_MISSING  => $exception->stackMissing(),
            JSONException::LOCALS_MISSING => $exception->localsMissing(),
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
                JSONStackFrame::FUNCTION_NAME => $frame->functionName(),
                JSONStackFrame::CLASS_NAME    => $frame->className(),
                JSONStackFrame::IS_STATIC     => $frame->isStatic(),
                JSONStackFrame::LOCATION      => $this->serializeCodeLocation($frame->location()),
                JSONStackFrame::OBJECT        => $this->serializeObject($frame->object()),
                JSONStackFrame::ARGS          => $this->serializeArgs($frame->arguments()),
                JSONStackFrame::ARGS_MISSING  => $frame->argumentsMissing(),
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
            JSONGlobals::GLOBAL_VARIABLES          => array(),
            JSONGlobals::STATIC_PROPERTIES         => array(),
            JSONGlobals::STATIC_VARIABLES          => array(),
            JSONGlobals::GLOBAL_VARIABLES_MISSING  => $globals->staticPropertiesMissing(),
            JSONGlobals::STATIC_PROPERTIES_MISSING => $globals->staticVariablesMissing(),
            JSONGlobals::STATIC_VARIABLES_MISSING  => $globals->globalVariablesMissing(),
        );

        foreach ($globals->staticProperties() as $p)
            $result[JSONGlobals::STATIC_PROPERTIES][] = $this->serializeObjectProperty($p);

        foreach ($globals->staticVariables() as $v)
            $result[JSONGlobals::STATIC_VARIABLES][] = array(
                                                           JSONStaticVariable::FUNCTION_NAME => $v->functionName(),
                                                           JSONStaticVariable::CLASS_NAME    => $v->className(),
                                                       ) + $this->serializeVariable($v);

        foreach ($globals->globalVariables() as $v)
            $result[JSONGlobals::GLOBAL_VARIABLES][] = $this->serializeVariable($v);

        return $result;
    }

    function visitException(ValueException $exception) {
        return array(
            JSONValue::PROP_TYPE      => JSONValue::TYPE_EXCEPTION,
            JSONValue::PROP_EXCEPTION => $this->serializeException($exception),
        );
    }

    function visitString(ValueString $string) {
        $json =& $this->json[self::STRINGS][$string->id()];

        if ($json === null) {
            $json = array(
                JSONString::BYTES         => $string->bytes(),
                JSONString::BYTES_MISSING => $string->bytesMissing(),
            );
        }

        return array(
            JSONValue::PROP_TYPE   => JSONValue::TYPE_STRING,
            JSONValue::PROP_STRING => $string->id(),
        );
    }

    function visitInt($int) {
        return array(
            JSONValue::PROP_TYPE => JSONValue::TYPE_INT,
            JSONValue::PROP_INT  => $int,
        );
    }

    function visitNull() {
        return array(
            JSONValue::PROP_TYPE => JSONValue::TYPE_NULL
        );
    }

    function visitUnknown() {
        return array(
            JSONValue::PROP_TYPE => JSONValue::TYPE_UNKNOWN
        );
    }

    function visitFloat($float) {
        if ($float === INF)
            return array(JSONValue::PROP_TYPE => JSONValue::TYPE_POS_INF);
        else if ($float === -INF)
            return array(JSONValue::PROP_TYPE => JSONValue::TYPE_NEG_INF);
        else if (is_nan($float))
            return array(JSONValue::PROP_TYPE => JSONValue::TYPE_NAN);
        else
            return array(JSONValue::PROP_TYPE => JSONValue::TYPE_FLOAT, JSONValue::PROP_FLOAT => $float);
    }

    function visitResource(ValueResource $resource) {
        return array(
            JSONValue::PROP_TYPE     => JSONValue::TYPE_RESOURCE,
            JSONValue::PROP_RESOURCE => array(
                JSONResource::TYPE => $resource->type(),
                JSONResource::ID   => $resource->id(),
            ),
        );
    }

    function visitBool($bool) {
        return array(
            JSONValue::PROP_TYPE => $bool ? JSONValue::TYPE_TRUE : JSONValue::TYPE_FALSE,
        );
    }

    private function serializeCodeLocation(ValueCodeLocation $location = null) {
        return $location instanceof ValueCodeLocation
            ? array(
                JSONCodeLocation::FILE   => $location->file(),
                JSONCodeLocation::LINE   => $location->line(),
                JSONCodeLocation::SOURCE => $location->sourceCode(),
            )
            : null;
    }

    private function serializeObjectProperty(ValueObjectProperty $p) {
        return array(
                   JSONObjectProperty::CLASS_NAME => $p->className(),
                   JSONObjectProperty::ACCESS     => $p->access(),
                   JSONObjectProperty::IS_DEFAULT => $p->isDefault(),
               ) + $this->serializeVariable($p);
    }

    private function serializeVariable(ValueVariable $v) {
        return array(
            JSONVariable::NAME  => $v->name(),
            JSONVariable::VALUE => $v->value()->acceptVisitor($this),
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
    const LINE   = 'line';
    const FILE   = 'file';
    const SOURCE = 'sourceCode';

    function line() { return $this->json[self::LINE]; }

    function file() { return $this->json[self::FILE]; }

    function sourceCode() { return $this->json[self::SOURCE]; }
}

class JSONException extends JSONParse implements ValueException {
    const CLASS_NAME     = 'className';
    const CODE           = 'code';
    const MESSAGE        = 'message';
    const PREVIOUS       = 'previous';
    const LOCATION       = 'location';
    const GLOBALS        = 'globals';
    const LOCALS         = 'locals';
    const STACK          = 'stack';
    const STACK_MISSING  = 'stackMissing';
    const LOCALS_MISSING = 'localsMissing';

    function className() { return $this->json[self::CLASS_NAME]; }

    function code() { return $this->json[self::CODE]; }

    function message() { return $this->json[self::MESSAGE]; }

    function previous() {
        $previous = $this->json[self::PREVIOUS];

        return $previous ? new self($this->root, $previous) : null;
    }

    function location() { return new JSONCodeLocation($this->root, $this->json[self::LOCATION]); }

    function globals() {
        $globals = $this->json[self::GLOBALS];

        return $globals ? new JSONGlobals($this->root, $globals) : null;
    }

    function locals() {
        $locals = $this->json[self::LOCALS];

        if (!is_array($locals))
            return null;

        $result = array();

        foreach ($locals as $json)
            $result[] = new JSONVariable($this->root, $json);

        return $result;
    }

    function stack() {
        $result = array();

        foreach ($this->json[self::STACK] as $json)
            $result[] = new JSONStackFrame($this->root, $json);

        return $result;
    }

    function stackMissing() { return $this->json[self::STACK_MISSING]; }

    function localsMissing() { return $this->json[self::LOCALS_MISSING]; }
}

class JSONStackFrame extends JSONParse implements ValueStackFrame {
    const ARGS          = 'args';
    const FUNCTION_NAME = 'functionName';
    const CLASS_NAME    = 'className';
    const IS_STATIC     = 'isStatic';
    const LOCATION      = 'location';
    const OBJECT        = 'object';
    const ARGS_MISSING  = 'argsMissing';

    function arguments() {
        $args = $this->json[self::ARGS];

        if (!is_array($args))
            return null;

        $result = array();

        foreach ($args as $json)
            $result[] = new JSONValue($this->root, $json);

        return $result;
    }

    function functionName() { return $this->json[self::FUNCTION_NAME]; }

    function className() { return $this->json[self::CLASS_NAME]; }

    function isStatic() { return $this->json[self::IS_STATIC]; }

    function location() {
        $location = $this->json[self::LOCATION];

        return $location ? new JSONCodeLocation($this->root, $location) : null;
    }

    function object() {
        $object = $this->json[self::OBJECT];

        return $object ? new JSONObject($this->root, $object) : null;
    }

    function argumentsMissing() { return $this->json[self::ARGS_MISSING]; }
}

class JSONGlobals extends JSONParse implements ValueGlobals {
    const STATIC_PROPERTIES         = 'staticProperties';
    const STATIC_VARIABLES          = 'staticVariables';
    const GLOBAL_VARIABLES          = 'globalVariables';
    const STATIC_PROPERTIES_MISSING = 'staticPropertiesMissing';
    const STATIC_VARIABLES_MISSING  = 'staticVariablesMissing';
    const GLOBAL_VARIABLES_MISSING  = 'globalVariablesMissing';

    function staticProperties() {
        $result = array();

        foreach ($this->json[self::STATIC_PROPERTIES] as $json)
            $result[] = new JSONObjectProperty($this->root, $json);

        return $result;
    }

    function staticVariables() {
        $result = array();

        foreach ($this->json[self::STATIC_VARIABLES] as $json)
            $result[] = new JSONStaticVariable($this->root, $json);

        return $result;
    }

    function globalVariables() {
        $result = array();

        foreach ($this->json[self::GLOBAL_VARIABLES] as $json)
            $result[] = new JSONVariable($this->root, $json);

        return $result;
    }

    function staticPropertiesMissing() { return $this->json[self::STATIC_PROPERTIES_MISSING]; }

    function staticVariablesMissing() { return $this->json[self::STATIC_VARIABLES_MISSING]; }

    function globalVariablesMissing() { return $this->json[self::GLOBAL_VARIABLES_MISSING]; }
}

class JSONVariable extends JSONParse implements ValueVariable {
    const NAME  = 'name';
    const VALUE = 'value';

    function name() { return $this->json[self::NAME]; }

    function value() { return new JSONValue($this->root, $this->json[self::VALUE]); }
}

class JSONStaticVariable extends JSONVariable implements ValueStaticVariable {
    const FUNCTION_NAME = 'functionName';
    const CLASS_NAME    = 'className';

    function functionName() { return $this->json[self::FUNCTION_NAME]; }

    function className() { return $this->json[self::CLASS_NAME]; }
}

class JSONResource extends JSONParse implements ValueResource {
    const TYPE = 'type';
    const ID   = 'id';

    function type() { return $this->json[self::TYPE]; }

    function id() { return $this->json[self::ID]; }
}

class JSONObject extends JSONParse implements ValueObject {
    const CLASS_NAME         = 'className';
    const PROPERTIES         = 'properties';
    const HASH               = 'hash';
    const PROPERTIES_MISSING = 'propertiesMissing';

    private $id;

    function __construct($root, $json) {
        $this->id = $json;
        $json     = $root['objects'][$json];
        parent::__construct($root, $json);
    }

    function className() { return $this->json[self::CLASS_NAME]; }

    function properties() {
        $result = array();

        foreach ($this->json[self::PROPERTIES] as $property)
            $result[] = new JSONObjectProperty($this->root, $property);

        return $result;
    }

    function hash() { return $this->json[self::HASH]; }

    function id() { return $this->id; }

    function propertiesMissing() { return $this->json[self::PROPERTIES_MISSING]; }
}

class JSONObjectProperty extends JSONVariable implements ValueObjectProperty {
    const ACCESS     = 'access';
    const CLASS_NAME = 'className';
    const IS_DEFAULT = 'isDefault';

    function access() { return $this->json[self::ACCESS]; }

    function className() { return $this->json[self::CLASS_NAME]; }

    function isDefault() { return $this->json[self::IS_DEFAULT]; }
}

class JSONArray extends JSONParse implements ValueArray {
    const IS_ASSOCIATIVE  = 'isAssociative';
    const ENTRIES         = 'entries';
    const ENTRIES_MISSING = 'entriesMissing';

    private $id;

    function __construct($root, $json) {
        $this->id = $json;
        $json     = $root['arrays'][$json];
        parent::__construct($root, $json);
    }

    function isAssociative() { return $this->json[self::IS_ASSOCIATIVE]; }

    function id() { return $this->id; }

    function entries() {
        $result = array();

        foreach ($this->json[self::ENTRIES] as $entry)
            $result[] = new JSONArrayEntry($this->root, $entry);

        return $result;
    }

    function entriesMissing() { return $this->json[self::ENTRIES_MISSING]; }
}

class JSONArrayEntry extends JSONParse implements ValueArrayEntry {
    const KEY   = 'key';
    const VALUE = 'value';

    function key() { return new JSONValue($this->root, $this->json[self::KEY]); }

    function value() { return new JSONValue($this->root, $this->json[self::VALUE]); }
}

class JSONValue extends JSONParse implements ValueImpl {
    const PROP_TYPE      = 'type';
    const PROP_OBJECT    = 'object';
    const PROP_EXCEPTION = 'exception';
    const PROP_FLOAT     = 'float';
    const PROP_ARRAY     = 'array';
    const PROP_RESOURCE  = 'resource';
    const PROP_STRING    = 'string';
    const PROP_INT       = 'int';
    const TYPE_OBJECT    = 'object';
    const TYPE_NEG_INF   = '-inf';
    const TYPE_POS_INF   = '+inf';
    const TYPE_NAN       = 'nan';
    const TYPE_FLOAT     = 'float';
    const TYPE_ARRAY     = 'array';
    const TYPE_EXCEPTION = 'exception';
    const TYPE_RESOURCE  = 'resource';
    const TYPE_UNKNOWN   = 'unknown';
    const TYPE_NULL      = 'null';
    const TYPE_INT       = 'int';
    const TYPE_TRUE      = 'true';
    const TYPE_FALSE     = 'false';
    const TYPE_STRING    = 'bytes';

    function acceptVisitor(ValueVisitor $visitor) {
        switch ($this->json[self::PROP_TYPE]) {
            case self::TYPE_OBJECT:
                return $visitor->visitObject(new JSONObject($this->root, $this->json[self::PROP_OBJECT]));
            case self::TYPE_NEG_INF:
                return $visitor->visitFloat(-INF);
            case self::TYPE_POS_INF:
                return $visitor->visitFloat(INF);
            case self::TYPE_NAN:
                return $visitor->visitFloat(NAN);
            case self::TYPE_FLOAT:
                return $visitor->visitFloat($this->json[self::PROP_FLOAT]);
            case self::TYPE_ARRAY:
                return $visitor->visitArray(new JSONArray($this->root, $this->json[self::PROP_ARRAY]));
            case self::TYPE_EXCEPTION:
                return $visitor->visitException(new JSONException($this->root, $this->json[self::PROP_EXCEPTION]));
            case self::TYPE_RESOURCE:
                return $visitor->visitResource(new JSONResource($this->root, $this->json[self::PROP_RESOURCE]));
            case self::TYPE_UNKNOWN:
                return $visitor->visitUnknown();
            case self::TYPE_NULL:
                return $visitor->visitNull();
            case self::TYPE_INT:
                return $visitor->visitInt($this->json[self::PROP_INT]);
            case self::TYPE_TRUE:
                return $visitor->visitBool(true);
            case self::TYPE_FALSE:
                return $visitor->visitBool(false);
            case self::TYPE_STRING:
                return $visitor->visitString(new JSONString($this->root, $this->json[self::PROP_STRING]));
            default:
                throw new Exception("Unknown type: {$this->json[self::PROP_TYPE]}");
        }
    }
}

class JSONString extends JSONParse implements ValueString {
    const BYTES         = 'bytes';
    const BYTES_MISSING = 'bytesMissing';

    private $id;

    function __construct($root, $json) {
        $this->id = $json;
        $json     = $root['strings'][$json];
        parent::__construct($root, $json);
    }

    function id() { return $this->id; }

    function bytes() { return $this->json[self::BYTES]; }

    function bytesMissing() { return $this->json[self::BYTES_MISSING]; }
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

    static function encodePretty($value, $indent = '') {
        if (is_object($value))
            $value = get_object_vars($value);

        if (is_array($value)) {
            if (empty($value))
                return '[]';

            if (self::isAssoc($value)) {
                $result = "{\n";

                $i = 0;
                foreach ($value as $k => $v) {
                    $ppk   = self::encodePretty("$k", "$indent    ");
                    $ppv   = self::encodePretty($v, "$indent    ");
                    $comma = $i == count($value) - 1 ? "" : ",";
                    $result .= "$indent    $ppk: $ppv$comma\n";
                    $i++;
                }
                $result .= "$indent}";
            } else {
                $result = "[\n";

                $i = 0;
                foreach ($value as $v) {
                    $p     = self::encodePretty($v, "$indent    ");
                    $comma = $i == count($value) - 1 ? "" : ",";
                    $result .= "$indent    $p$comma\n";
                    $i++;
                }
                $result .= "$indent]";
            }

            return $result;
        }

        return self::encode($value);
    }

    static function isAssoc(array $array) {
        $i = 0;
        foreach ($array as $k => $v)
            if ($k !== $i++)
                return true;

        return false;
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

