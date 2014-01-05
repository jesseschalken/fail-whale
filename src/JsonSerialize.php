<?php

namespace ErrorHandler;

class JsonSerializationState {
    public $root = array(
        'arrays' => array(),
        'objects' => array(),
    );
    public $objectIDs = array();
    public $arrayIDs = array();
}

class JsonDeSerializationState {
    /** @var ValueObject[] */
    public $finishedObjects = array();
    /** @var ValueArray[] */
    public $finishedArrays = array();
    public $root;

    function constructValue($v) {
        if (is_float($v))
            return new ValueFloat;

        if (is_int($v))
            return new ValueInt;

        if (is_bool($v))
            return new ValueBool;

        if (is_null($v))
            return new ValueNull;

        if (is_string($v))
            return new ValueString;

        switch ($v[0]) {
            case 'object':
                $object =& $this->finishedObjects[$v[1]];

                return $object === null ? new ValueObject : $object;
            case '-inf':
            case '+inf':
            case 'nan':
            case 'float':
                return new ValueFloat;
            case 'array':
                $array =& $this->finishedArrays[$v[1]];

                return $array === null ? new ValueArray : $array;
            case 'exception':
                return new ValueException;
            case 'resource':
                return new ValueResource;
            case 'unknown':
                return new ValueUnknown;
            case 'null':
                return new ValueNull;
            case 'int':
                return new ValueInt;
            case 'bool':
                return new ValueBool;
            case 'string':
                return new ValueString;
            default:
                throw new Exception("Unknown type: {$v[0]}");
        }
    }
}

class JsonSchemaObject implements JsonSerializable {
    /** @var JsonSerializable[] */
    private $properties = array();

    function toJSON(JsonSerializationState $s) {
        $result = array();

        foreach ($this->properties as $k => $p)
            $result[$k] = $p->toJSON($s);

        return $result;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        foreach ($this->properties as $k => $p)
            $p->fromJSON($s, $x[$k]);
    }

    function bindRef($property, &$ref) {
        $this->bind($property, new JsonRef($ref));
    }

    function bind($property, JsonSerializable $j) {
        $this->properties[$property] = $j;
    }

    function bindObject($property, &$ref, $constructor, $nullable) {
        $this->bind($property, new JsonRefObject($ref, $constructor, $nullable));
    }

    function bindObjectList($property, &$ref, $constructor) {
        $this->bind($property, new JsonRefObjectList($ref, $constructor));
    }

    function bindValueList($string, &$args) {
        $this->bind($string, new JsonRefValueList($args));
    }

    function bindValue($string, &$value) {
        $this->bind($string, new JsonRefValue($value));
    }
}

interface JsonSerializable {
    function toJSON(JsonSerializationState $s);

    function fromJSON(JsonDeSerializationState $s, $x);
}

class JsonRef implements JsonSerializable {
    private $ref;

    function __construct(&$ref) {
        $this->ref =& $ref;
    }

    protected function get() { return $this->ref; }

    protected function set($x) { $this->ref = $x; }

    function toJSON(JsonSerializationState $s) {
        return $this->ref;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->ref = $x;
    }
}

class JsonRefObject implements JsonSerializable {
    private $constructor;
    private $ref;
    private $nullable;

    /**
     * @param JsonSerializable|null $ref
     * @param callable              $constructor
     * @param bool                  $nullable
     */
    function __construct(&$ref, \Closure $constructor, $nullable = false) {
        $this->constructor = $constructor;
        $this->ref         =& $ref;
        $this->nullable    = $nullable;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->ref !== null ? $this->ref->toJSON($s) : null;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        if ($x === null && $this->nullable) {
            $this->ref = null;
        } else {
            /** @var JsonSerializable $object */
            $constructor = $this->constructor;
            $object      = $constructor($s, $x);
            $this->ref   = $object;
            $this->ref->fromJSON($s, $x);
        }
    }
}

class JsonRefValue extends JsonRefObject {
    function __construct(&$ref) {
        $constructor = function (JsonDeSerializationState $s, $v) { return $s->constructValue($v); };

        parent::__construct($ref, $constructor, false);
    }
}

class JsonRefObjectList implements JsonSerializable {
    private $ref;
    private $constructor;

    /**
     * @param JsonSerializable[]|null $ref
     * @param callable                $constructor
     */
    function __construct(&$ref, \Closure $constructor) {
        $this->ref         =& $ref;
        $this->constructor = $constructor;
    }

    function toJSON(JsonSerializationState $s) {
        if ($this->ref === null)
            return null;

        $result = array();

        foreach ($this->ref as $k => $v) {
            $result[$k] = $v->toJSON($s);
        }

        return $result;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        if ($x === null) {
            $this->ref = null;
        } else {
            $this->ref = array();

            foreach ($x as $k => $v) {
                /** @var JsonSerializable $object */
                $constructor = $this->constructor;
                $object      = $constructor($s, $v);
                $object->fromJSON($s, $v);
                $this->ref[$k] = $object;
            }
        }
    }
}

class JsonRefValueList extends JsonRefObjectList {
    function __construct(&$ref) {
        $constructor = function (JsonDeSerializationState $s, $v) { return $s->constructValue($v); };

        parent::__construct($ref, $constructor);
    }
}

class Json {
    /**
     * @param mixed $value
     *
     * @return string
     */
    static function stringify($value) {
        $result = json_encode(self::prepare($value));

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
        if (is_string($value))
            return utf8_encode($value);

        if (is_float($value) || is_int($value) || is_null($value) || is_bool($value))
            return $value;

        if (is_array($value)) {
            $result = array();

            foreach ($value as $k => $v)
                $result[self::prepare($k)] = self::prepare($v);

            return $result;
        }

        throw new \Exception("Invalid JSON value");
    }

    private static function checkError() {
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new \Exception("JSON Error", json_last_error());
    }

    /**
     * @param mixed $result
     *
     * @return mixed
     */
    private static function unprepare($result) {
        if (is_string($result))
            return utf8_decode($result);

        if (is_array($result)) {
            $result2 = array();

            foreach ($result as $k => $v)
                $result2[self::unprepare($k)] = self::unprepare($v);

            return $result2;
        }

        return $result;
    }
}

