<?php

namespace ErrorHandler;

class JsonSerializationState {
    public $root = array(
        'arrays'  => array(),
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
            return new ValueFloat($v);

        if (is_int($v))
            return new ValueInt($v);

        if (is_bool($v))
            return new ValueBool($v);

        if (is_null($v))
            return new ValueNull;

        if (is_string($v))
            return new ValueString($v);

        switch ($v[0]) {
            case 'object':
                return ValueObject::fromJson($this, $v);
            case '-inf':
            case '+inf':
            case 'nan':
            case 'float':
                return ValueFloat::fromJson($v);
            case 'array':
                return ValueArray::fromJson($this, $v);
            case 'exception':
                return ValueException::fromJson($this, $v);
            case 'resource':
                return ValueResource::fromJson($this, $v);
            case 'unknown':
                return new ValueUnknown;
            case 'null':
                return new ValueNull;
            case 'int':
                return new ValueInt($v[1]);
            case 'bool':
                return new ValueBool($v[1]);
            case 'string':
                return new ValueString($v[1]);
            default:
                throw new Exception("Unknown type: {$v[0]}");
        }
    }
}

class JsonSchemaObject implements JsonSerializable {
    /** @var JsonWritable[] */
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

    function bind($property, JsonWritable $j) {
        $this->properties[$property] = $j;
    }

    function bindObject($property, &$ref, $constructor) {
        $this->bind($property, new JsonRefObject($ref, $constructor));
    }

    function bindObjectList($property, &$ref, $constructor) {
        $this->bind($property, new JsonRefObjectList($ref, $constructor));
    }

    function bindValueList($string, &$args) {
        $this->bindObjectList($string, $args, function (JsonDeSerializationState $j, $v) {
            return $j->constructValue($v);
        });
    }

    function bindValue($string, &$value) {
        $this->bindObject($string, $value, function (JsonDeSerializationState $j, $v) {
            return $j->constructValue($v);
        });
    }
}

interface JsonSerializable {
    function toJSON(JsonSerializationState $s);
}

interface JsonWritable extends JsonSerializable {
    function fromJSON(JsonDeSerializationState $s, $x);
}

class JsonRef implements JsonWritable {
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

class JsonRefObject implements JsonWritable {
    private $constructor;
    private $ref;

    /**
     * @param JsonSerializable|null $ref
     * @param callable              $constructor
     */
    function __construct(&$ref, \Closure $constructor) {
        $this->constructor = $constructor;
        $this->ref         =& $ref;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->ref !== null ? $this->ref->toJSON($s) : null;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $constructor = $this->constructor;
        $this->ref   = $constructor($s, $x);
    }
}

class JsonRefObjectList implements JsonWritable {
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
                $constructor   = $this->constructor;
                $this->ref[$k] = $constructor($s, $v);
            }
        }
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

