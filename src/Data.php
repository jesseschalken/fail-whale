<?php

namespace FailWhale;

class Base {
    static function convertJSONs(&$json) {
        if ($json) {
            foreach ($json as &$json2)
                static::convertJSON($json2);
        }
    }

    static function convertJSON(&$json) {
        if ($json) {
            /** @var self $self */
            $self = new static;
            $self->pushJSON($json);;
            $json = $self;
        }
    }

    function pushJSON(array $json) {
        foreach (get_object_vars($this) as $name => $value)
            if (isset($json[$name]))
                $this->$name = $json[$name];
            else
                $this->$name = null;
    }

    function pullJSON() {
        $json = get_object_vars($this);
        foreach ($json as $k => &$value) {
            if ($value === null) {
                unset($json[$k]);
            } else if (is_array($value)) {
                foreach ($value as &$v)
                    if ($v instanceof self)
                        $v = $v->pullJSON();
            } else if ($value instanceof self) {
                $value = $value->pullJSON();
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

    function pushJSON(array $json) {
        parent::pushJSON($json);
        ValueImpl::convertJSON($this->root);
        String1::convertJSONs($this->strings);
        Object1::convertJSONs($this->objects);
        Array1::convertJSONs($this->arrays);
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

    function pushJSON(array $json) {
        parent::pushJSON($json);
        ArrayEntry::convertJSONs($this->entries);
    }
}

class ArrayEntry extends Base {
    /** @var ValueImpl */
    public $key;
    /** @var ValueImpl */
    public $value;

    function pushJSON(array $json) {
        parent::pushJSON($json);
        ValueImpl::convertJSON($this->key);
        ValueImpl::convertJSON($this->value);
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

    function pushJSON(array $json) {
        parent::pushJSON($json);
        Property::convertJSONs($this->properties);
    }
}

class Variable extends Base {
    /** @var string */
    public $name;
    /** @var ValueImpl */
    public $value;

    function pushJSON(array $json) {
        parent::pushJSON($json);
        ValueImpl::convertJSON($this->value);
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

    function pushJSON(array $json) {
        parent::pushJSON($json);
        Property::convertJSONs($this->staticProperties);
        StaticVariable::convertJSONs($this->staticVariables);
        Variable::convertJSONs($this->globalVariables);
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

    function pushJSON(array $json) {
        parent::pushJSON($json);
        Variable::convertJSONs($this->locals);
        Globals::convertJSON($this->globals);
        Stack::convertJSONs($this->stack);
        Location::convertJSON($this->location);
        self::convertJSON($this->previous);
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

    function pushJSON(array $json) {
        parent::pushJSON($json);
        ValueImpl::convertJSONs($this->args);
        Location::convertJSON($this->location);
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

    function pushJSON(array $json) {
        parent::pushJSON($json);
        ExceptionImpl::convertJSON($this->exception);
        Resource1::convertJSON($this->resource);
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

