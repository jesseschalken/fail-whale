<?php

namespace FailWhale\Data;

use FailWhale\_Internal\JsonSerializable;

abstract class Base implements JsonSerializable {
    /**
     * Alternative to ::class for PHP < 5.5
     * @return string
     */
    public final static function class_() {
        return get_called_class();
    }

    /**
     * @return string[]
     */
    public final static function classes() {
        return array(
            Root::class_(),
            String_::class_(),
            Array_::class_(),
            ArrayEntry::class_(),
            Object_::class_(),
            Variable::class_(),
            Property::class_(),
            StaticVariable::class_(),
            Globals::class_(),
            ExceptionData::class_(),
            Exception_::class_(),
            Stack::class_(),
            FunctionArg::class_(),
            Value_::class_(),
            Resource_::class_(),
            Location::class_(),
            CodeLine::class_(),
        );
    }
}

class Root extends Base {
    public static function jsonType() { return 'Root'; }

    /** @var Value_ */
    public $root;
    /** @var String_[] */
    public $strings = array();
    /** @var Object_[] */
    public $objects = array();
    /** @var Array_[] */
    public $arrays = array();
}

class String_ extends Base {
    public static function jsonType() { return 'String'; }

    /** @var string */
    public $bytes;
    /** @var int */
    public $bytesMissing = 0;
}

class Array_ extends Base {
    public static function jsonType() { return 'Array'; }

    /** @var int */
    public $entriesMissing = 0;
    /** @var ArrayEntry[] */
    public $entries = array();
    /** @var bool */
    public $isAssociative;
}

class ArrayEntry extends Base {
    public static function jsonType() { return 'ArrayEntry'; }

    /** @var Value_ */
    public $key;
    /** @var Value_ */
    public $value;
}

class Object_ extends Base {
    public static function jsonType() { return 'Object'; }

    /** @var string */
    public $hash;
    /** @var string */
    public $className;
    /** @var Property[] */
    public $properties = array();
    /** @var int */
    public $propertiesMissing = 0;
}

class Variable extends Base {
    public static function jsonType() { return 'Variable'; }

    /** @var string */
    public $name;
    /** @var Value_ */
    public $value;
}

class Property extends Variable {
    public static function jsonType() { return 'Property'; }

    /** @var string */
    public $className;
    /** @var string */
    public $access;
    /** @var bool */
    public $isDefault;
}

class StaticVariable extends Variable {
    public static function jsonType() { return 'StaticVariable'; }

    /** @var string */
    public $className;
    /** @var string */
    public $functionName;
}

class Globals extends Base {
    public static function jsonType() { return 'Globals'; }

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
}

class ExceptionData extends Base {
    public static function jsonType() { return 'ExceptionData'; }

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
}

class Exception_ extends Base {
    public static function jsonType() { return 'Exception'; }

    /** @var ExceptionData[] */
    public $exceptions = array();
    /** @var Globals */
    public $globals;
}

class Stack extends Base {
    public static function jsonType() { return 'Stack'; }

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
    /** @var Variable[] */
    public $locals;
    /** @var int */
    public $localsMissing = 0;
}

class FunctionArg extends Base {
    public static function jsonType() { return 'FunctionArg'; }

    /** @var string */
    public $name;
    /** @var Value_ */
    public $value;
    /** @var string */
    public $typeHint;
    /** @var bool */
    public $isReference;
}

class Value_ extends Base {
    public static function jsonType() { return 'Value'; }

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
    public static function jsonType() { return 'Resource'; }

    /** @var string */
    public $type;
    /** @var int */
    public $id;
}

class Location extends Base {
    public static function jsonType() { return 'Location'; }

    /** @var string */
    public $file;
    /** @var int */
    public $line;
    /** @var CodeLine[] */
    public $source;
}

class CodeLine extends Base {
    public static function jsonType() { return 'CodeLine'; }

    /** @var int */
    public $line;
    /** @var string */
    public $code;
}