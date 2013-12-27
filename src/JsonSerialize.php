<?php
 
namespace ErrorHandler;

class JsonSerialize {
    static function fromJsonWhole(array $value) {
        $self                    = new self;
        $self->serializedObjects = $value['objects'];
        $self->serializedArrays  = $value['arrays'];

        return $self->fromJsonValue($value['root']);
    }

    static function toJsonWhole(Value $v) {
        $self = new self;
        $root = $self->toJsonValue($v);

        return array(
            'root'    => $root,
            'arrays'  => $self->serializedArrays,
            'objects' => $self->serializedObjects,
        );
    }

    /** @var mixed[] */
    private $serializedArrays = array();
    /** @var mixed[] */
    private $serializedObjects = array();

    /** @var ValueArray[] */
    private $mapIndexToArray = array();
    /** @var ValueObject[] */
    private $mapIndexToObject = array();

    /** @var int[] */
    private $mapArrayToIndex = array();
    /** @var int[] */
    private $mapObjectToIndex = array();

    private function __construct() { }

    /**
     * @param array $v
     *
     * @throws Exception
     * @return Value
     */
    function fromJsonValue(array $v) {
        switch ($v['type']) {
            case 'object':
                $index = $v['object'];

                if (isset($this->mapIndexToObject[$index]))
                    return $this->mapIndexToObject[$index];

                return ValueObject::fromJsonValueImpl($this, $index, $this->serializedObjects[$index]);
            case 'float':
                $value = $v['float'];
                if ($value === 'INF')
                    return new ValueFloat(INF);
                else if ($value === '-INF')
                    return new ValueFloat(-INF);
                else if ($value === 'NAN')
                    return new ValueFloat(NAN);
                else
                    return new ValueFloat((float)$value);
            case 'array':
                $index = $v['array'];

                if (isset($this->mapIndexToArray[$index]))
                    return $this->mapIndexToArray[$index];

                return ValueArray::fromJsonValueImpl($this, $index, $this->serializedArrays[$index]);
            case 'exception':
                return ValueException::fromJsonValueImpl($this, $v['exception']);
            case 'resource':
                return ValueResource::fromJsonValueImpl($v['resource']);
            case 'unknown':
                return new ValueUnknown;
            case 'null':
                return new ValueNull;
            case 'int':
                return new ValueInt((int)$v['int']);
            case 'bool':
                return new ValueBool((bool)$v['bool']);
            case 'string':
                return new ValueString($v['string']);
            default:
                throw new Exception("Unknown type: {$v['type']}");
        }
    }

    function toJsonValue(Value $value) {
        return $value->toJsonValueImpl($this);
    }

    function insertObject($index, ValueObject $self) {
        $this->mapIndexToObject[$index]      = $self;
        $this->mapObjectToIndex[$self->id()] = $index;
    }

    function insertArray($index, ValueArray $self) {
        $this->mapIndexToArray[$index]      = $self;
        $this->mapArrayToIndex[$self->id()] = $index;
    }

    function addArray(ValueArray $array) {
        if (isset($this->mapArrayToIndex[$array->id()]))
            return $this->mapArrayToIndex[$array->id()];

        $index = count($this->mapArrayToIndex);

        $this->insertArray($index, $array);

        $this->serializedArrays[$index] = $array->serializeArray($this);

        return $index;
    }

    function addObject(ValueObject $object) {
        if (isset($this->mapObjectToIndex[$object->id()]))
            return $this->mapObjectToIndex[$object->id()];

        $index = count($this->mapObjectToIndex);

        $this->insertObject($index, $object);

        $this->serializedObjects[$index] = $object->serializeObject($this);

        return $index;
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
