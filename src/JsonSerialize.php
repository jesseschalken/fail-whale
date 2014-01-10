<?php

namespace ErrorHandler;

interface JsonSerializable {
    function toJSON(JsonSerializationState $s);
}

final class JsonSerializationState {
    static function toJson(Value $v) {
        $self               = new self;
        $self->root['root'] = $v->toJSON($self);

        return $self->root;
    }

    public $root = array(
        'root'    => null,
        'arrays'  => array(),
        'objects' => array(),
    );
    public $objectIndexes = array();
    public $arrayIndexes = array();

    private function __construct() { }
}

final class JsonDeSerializationState {
    static function fromJson($parse) {
        $self       = new self;
        $self->root = $parse;

        return self::fromJson($self, $parse['root']);
    }

    /** @var ValueObject[] */
    public $finishedObjects = array();
    /** @var ValueArray[] */
    public $finishedArrays = array();
    public $root;

    private function __construct() { }
}

final class JsonSchemaObject implements JsonSerializable {
    /** @var JsonSchema[] */
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

    function bind($property, JsonSchema $j) {
        $this->properties[$property] = $j;
    }

    function bindObject($property, &$ref, $constructor) {
        $this->bind($property, new JsonRefObject($ref, $constructor));
    }

    function bindObjectList($property, &$ref, $constructor) {
        $this->bind($property, new JsonRefObjectList($ref, $constructor));
    }

    function bindValueList($string, &$args) {
        $this->bindObjectList($string, $args, function ($j, $v) { return Value::fromJson($j, $v); });
    }

    function bindValue($string, &$value) {
        $this->bindObject($string, $value, function ($j, $v) { return Value::fromJson($j, $v); });
    }
}

abstract class JsonSchema implements JsonSerializable {
    abstract function fromJSON(JsonDeSerializationState $s, $x);
}

final class JsonRef extends JsonSchema {
    private $ref;

    function __construct(&$ref) {
        $this->ref =& $ref;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->ref;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->ref = $x;
    }

    protected function get() { return $this->ref; }

    protected function set($x) { $this->ref = $x; }
}

final class JsonRefObject extends JsonSchema {
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

final class JsonRefObjectList extends JsonSchema {
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

final class Json {
    /**
     * @param mixed $value
     *
     * @return string
     */
    static function stringify($value) {
        $flags = 0;

        if (defined('JSON_PRETTY_PRINT'))
            $flags |= JSON_PRETTY_PRINT;

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

