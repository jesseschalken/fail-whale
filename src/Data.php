<?php

namespace FailWhale;

class Base {
    static function jsons(&$json) {
        if ($json) {
            foreach ($json as &$json2)
                static::json($json2);
        }
    }

    static function json(&$json) {
        if ($json) {
            /** @var self $self */
            $self = new static;
            $self->pushJson($json);;
            $json = $self;
        }
    }

    function pushJson(array $json) {
        foreach (get_object_vars($this) as $name => $value)
            if (isset($json[$name]))
                $this->$name = $json[$name];
            else
                $this->$name = null;
    }

    function pullJson() {
        $json = get_object_vars($this);
        foreach ($json as $k => &$value) {
            if ($value === null) {
                unset($json[$k]);
            } else if (is_array($value)) {
                foreach ($value as &$v)
                    if ($v instanceof self)
                        $v = $v->pullJson();
            } else if ($value instanceof self) {
                $value = $value->pullJson();
            }
        }
        return $json;
    }
}

class Root extends Base {
    /** @var ValueImpl */
    public $root;
    /** @var String1[] */
    public $strings = array();
    /** @var Object1[] */
    public $objects = array();
    /** @var Array1[] */
    public $arrays = array();

    function pushJson(array $json) {
        parent::pushJson($json);
        ValueImpl::json($this->root);
        String1::jsons($this->strings);
        Object1::jsons($this->objects);
        Array1::jsons($this->arrays);
    }
}

class String1 extends Base {
    /** @var string */
    public $bytes;
    /** @var int */
    public $bytesMissing = 0;
}

class Array1 extends Base {
    /** @var int */
    public $entriesMissing = 0;
    /** @var ArrayEntry[] */
    public $entries = array();
    /** @var bool */
    public $isAssociative;

    function pushJson(array $json) {
        parent::pushJson($json);
        ArrayEntry::jsons($this->entries);
    }
}

class ArrayEntry extends Base {
    /** @var ValueImpl */
    public $key;
    /** @var ValueImpl */
    public $value;

    function pushJson(array $json) {
        parent::pushJson($json);
        ValueImpl::json($this->key);
        ValueImpl::json($this->value);
    }
}

class Object1 extends Base {
    /** @var string */
    public $hash;
    /** @var string */
    public $className;
    /** @var Property[] */
    public $properties = array();
    /** @var int */
    public $propertiesMissing = 0;

    function pushJson(array $json) {
        parent::pushJson($json);
        Property::jsons($this->properties);
    }
}

class Variable extends Base {
    /** @var string */
    public $name;
    /** @var ValueImpl */
    public $value;

    function pushJson(array $json) {
        parent::pushJson($json);
        ValueImpl::json($this->value);
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

    function pushJson(array $json) {
        parent::pushJson($json);
        Property::jsons($this->staticProperties);
        StaticVariable::jsons($this->staticVariables);
        Variable::jsons($this->globalVariables);
    }
}

class ExceptionImpl extends Base {
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
    /** @var ExceptionImpl */
    public $previous;

    function pushJson(array $json) {
        parent::pushJson($json);
        Variable::jsons($this->locals);
        Globals::json($this->globals);
        Stack::jsons($this->stack);
        Location::json($this->location);
        self::json($this->previous);
    }
}

class Stack extends Base {
    /** @var string */
    public $functionName;
    /** @var ValueImpl[] */
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

    function pushJson(array $json) {
        parent::pushJson($json);
        ValueImpl::jsons($this->args);
        Location::json($this->location);
    }
}

class ValueImpl extends Base {
    /** @var string */
    public $type;
    /** @var ExceptionImpl */
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
    /** @var Resource1 */
    public $resource;

    function pushJson(array $json) {
        parent::pushJson($json);
        ExceptionImpl::json($this->exception);
        Resource1::json($this->resource);
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

class Resource1 extends Base {
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

