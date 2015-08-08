<?php

namespace FailWhale\Data;

class Base {
    public static function fromArray($array) {
        static::convertArray($array);
        return $array;
    }

    protected static function convertArrays(&$arrays) {
        if (is_array($arrays)) {
            foreach ($arrays as &$v)
                static::convertArray($v);
        }
    }

    protected static function convertArray(&$array) {
        if (is_array($array)) {
            $self = new static;
            $self->importArray($array);
            $array = $self;
        }
    }

    protected function importArray(array $array) {
        foreach (get_object_vars($this) as $k => $v) {
            $this->$k = isset($array[$k]) ? $array[$k] : null;
        }
    }

    final function toArray() {
        return self::toArray_($this);
    }

    private static function toArray_($value) {
        if ($value instanceof self)
            $value = get_object_vars($value);

        if (is_array($value)) {
            $r = array();
            foreach ($value as $k => $v)
                if ($v !== null)
                    $r[$k] = self::toArray_($v);
            return $r;
        } else {
            return $value;
        }
    }
}

class Root extends Base {
    /** @var Value_ */
    public $root;
    /** @var String_[] */
    public $strings = array();
    /** @var Object_[] */
    public $objects = array();
    /** @var Array_[] */
    public $arrays = array();

    protected function importArray(array $array) {
        parent::importArray($array);
        Value_::convertArray($this->root);
        String_::convertArrays($this->strings);
        Object_::convertArrays($this->objects);
        Array_::convertArrays($this->arrays);
    }
}

class String_ extends Base {
    /** @var string */
    public $bytes;
    /** @var int */
    public $bytesMissing = 0;
}

class Array_ extends Base {
    /** @var int */
    public $entriesMissing = 0;
    /** @var ArrayEntry[] */
    public $entries = array();
    /** @var bool */
    public $isAssociative;

    protected function importArray(array $array) {
        parent::importArray($array);
        ArrayEntry::convertArrays($this->entries);
    }
}

class ArrayEntry extends Base {
    /** @var Value_ */
    public $key;
    /** @var Value_ */
    public $value;

    protected function importArray(array $array) {
        parent::importArray($array);
        Value_::convertArray($this->key);
        Value_::convertArray($this->value);
    }
}

class Object_ extends Base {
    /** @var string */
    public $hash;
    /** @var string */
    public $className;
    /** @var Property[] */
    public $properties = array();
    /** @var int */
    public $propertiesMissing = 0;

    protected function importArray(array $array) {
        parent::importArray($array);
        Property::convertArrays($this->properties);
    }
}

class Variable extends Base {
    /** @var string */
    public $name;
    /** @var Value_ */
    public $value;

    protected function importArray(array $array) {
        parent::importArray($array);
        Value_::convertArray($this->value);
    }
}

class Property extends Variable {
    /** @var string */
    public $className;
    /** @var string */
    public $access;
    /** @var bool */
    public $isDefault;
}

class StaticVariable extends Variable {
    /** @var string */
    public $className;
    /** @var string */
    public $functionName;
}

class Globals extends Base {
    /** @var Property[] */
    public $staticProperties;
    /** @var int */
    public $staticPropertiesMissing = 0;
    /** @var StaticVariable[] */
    public $staticVariables;
    /** @var int */
    public $staticVariablesMissing = 0;
    /** @var Variable[] */
    public $globalVariables;
    /** @var int */
    public $globalVariablesMissing = 0;

    protected function importArray(array $array) {
        parent::importArray($array);
        Property::convertArrays($this->staticProperties);
        StaticVariable::convertArrays($this->staticVariables);
        Variable::convertArrays($this->globalVariables);
    }
}

class Exception_ extends Base {
    /** @var Variable[] */
    public $locals;
    /** @var int */
    public $localsMissing = 0;
    /** @var Globals */
    public $globals;
    /** @var Stack[] */
    public $stack = array();
    /** @var int */
    public $stackMissing = 0;
    /** @var string */
    public $className;
    /** @var string */
    public $code;
    /** @var string */
    public $message;
    /** @var Location */
    public $location;
    /** @var Exception_ */
    public $previous;

    protected function importArray(array $array) {
        parent::importArray($array);
        Variable::convertArrays($this->locals);
        Globals::convertArray($this->globals);
        Stack::convertArrays($this->stack);
        Location::convertArray($this->location);
        self::convertArray($this->previous);
    }
}

class Stack extends Base {
    /** @var string */
    public $functionName;
    /** @var FunctionArg[] */
    public $args;
    /** @var int */
    public $argsMissing = 0;
    /** @var int */
    public $object;
    /** @var string */
    public $className;
    /** @var bool */
    public $isStatic;
    /** @var Location */
    public $location;

    protected function importArray(array $array) {
        parent::importArray($array);
        FunctionArg::convertArrays($this->args);
        Location::convertArray($this->location);
    }
}

class FunctionArg extends Base {
    /** @var string */
    public $name;
    /** @var Value_ */
    public $value;
    /** @var string */
    public $typeHint;
    /** @var bool */
    public $isReference;

    protected function importArray(array $array) {
        parent::importArray($array);
        Value_::convertArray($this->value);
    }
}

class Value_ extends Base {
    /** @var string */
    public $type;
    /** @var Exception_ */
    public $exception;
    /** @var int */
    public $object;
    /** @var int */
    public $array;
    /** @var int */
    public $string;
    /** @var int */
    public $int;
    /** @var float */
    public $float;
    /** @var Resource_ */
    public $resource;

    protected function importArray(array $array) {
        parent::importArray($array);
        Exception_::convertArray($this->exception);
        Resource_::convertArray($this->resource);
    }
}

class Type {
    const STRING    = 'string';
    const ARRAY1    = 'array';
    const OBJECT    = 'object';
    const INT       = 'int';
    const TRUE      = 'true';
    const FALSE     = 'false';
    const NULL      = 'null';
    const POS_INF   = '+inf';
    const NEG_INF   = '-inf';
    const NAN       = 'nan';
    const UNKNOWN   = 'unknown';
    const FLOAT     = 'float';
    const RESOURCE  = 'resource';
    const EXCEPTION = 'exception';
}

class Resource_ extends Base {
    /** @var string */
    public $type;
    /** @var int */
    public $id;
}

class Location extends Base {
    /** @var string */
    public $file;
    /** @var int */
    public $line;
    /** @var string[] */
    public $source;
}

