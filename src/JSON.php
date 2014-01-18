<?php

namespace ErrorHandler;

interface JSONSerializable {
    function toJSON(JSONSerialize $s);
}

final class JSONSerialize {
    /**
     * @param Value $v
     *
     * @return string
     */
    static function toJSON(Value $v) {
        $self               = new self;
        $self->root['root'] = $v->toJSON($self);

        return JSON::stringify($self->root);
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

final class JSONUnserialize {
    /**
     * @param string $parse
     *
     * @return Value
     */
    static function fromJSON($parse) {
        $self       = new self;
        $self->root = JSON::parse($parse);

        return Value::fromJSON($self, $self->root['root']);
    }

    /** @var ValueObject[] */
    public $finishedObjects = array();
    /** @var ValueArray[] */
    public $finishedArrays = array();
    public $root;

    private function __construct() { }
}

final class JSONSchema {
    /** @var callable[] */
    private $getters = array();
    /** @var callable[] */
    private $setters = array();

    function toJSON(JSONSerialize $s) {
        $result = array();

        foreach ($this->getters as $k => $p)
            $result[$k] = $p($s);

        return $result;
    }

    function fromJSON(JSONUnserialize $s, $x) {
        foreach ($this->setters as $k => $p)
            $p($s, $x[$k]);
    }

    /**
     * @param string $property
     * @param mixed  $ref
     */
    function bind($property, &$ref) {
        /** @noinspection PhpUnusedParameterInspection */
        $this->getters[$property] = function (JSONSerialize $s) use (&$ref) { return $ref; };
        /** @noinspection PhpUnusedParameterInspection */
        $this->setters[$property] = function (JSONUnserialize $s, $x) use (&$ref) { $ref = $x; };
    }

    /**
     * @param string           $property
     * @param JSONSerializable $ref
     * @param callable         $fromJSON
     */
    function bindObject($property, JSONSerializable &$ref = null, \Closure $fromJSON) {
        $this->getters[$property] = function (JSONSerialize $s) use (&$ref) {
            return $ref === null ? null : $ref->toJSON($s);
        };

        $this->setters[$property] = function (JSONUnserialize $s, $x) use (&$ref, $fromJSON) {
            $ref = $fromJSON($s, $x);
        };
    }

    /**
     * @param string                  $property
     * @param JSONSerializable[]|null $ref
     * @param callable                $fromJSON
     */
    function bindObjectList($property, array &$ref = null, \Closure $fromJSON) {
        $this->getters[$property] = function (JSONSerialize $s) use (&$ref) {
            if ($ref === null) {
                return null;
            } else {
                $result = array();
                foreach ($ref as $k => $v)
                    $result[$k] = $v->toJSON($s);

                return $result;
            }
        };

        $this->setters[$property] = function (JSONUnserialize $s, $x) use (&$ref, $fromJSON) {
            if ($x === null) {
                $ref = null;
            } else {
                $ref = array();

                foreach ($x as $k => $v)
                    $ref[$k] = $fromJSON($s, $v);
            }
        };
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
     * @throws \Exception
     * @return mixed
     */
    private static function unprepare($result) {
        if (is_string($result))
            return utf8_decode($result);

        if (is_float($result) || is_int($result) || is_null($result) || is_bool($result))
            return $result;

        if (is_array($result)) {
            $result2 = array();

            foreach ($result as $k => $v)
                $result2[self::unprepare($k)] = self::unprepare($v);

            return $result2;
        }

        throw new \Exception("Invalid JSON value");
    }
}

