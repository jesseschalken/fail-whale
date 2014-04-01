<?php

namespace ErrorHandler\JSON2;

use ErrorHandler\DummyClass1;
use ErrorHandler\DummyClass2;
use ErrorHandler\ExceptionHasFullTrace;
use ErrorHandler\ExceptionHasLocalVariables;

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
    /** @var Value */
    public $root;
    /** @var String1[] */
    public $strings = array();
    /** @var Object1[] */
    public $objects = array();
    /** @var Array1[] */
    public $arrays = array();

    function pushJson(array $json) {
        parent::pushJson($json);
        Value::json($this->root);
        String1::jsons($this->strings);
        Object1::jsons($this->objects);
        Array1::jsons($this->arrays);
    }
}

class Introspection {
    /** @var Root */
    private $root;
    private $stringIds = array();
    private $objectIds = array();
    private $arrayIds = array();

    function __construct() {
        $this->root = new Root;
    }

    function mockException() {
        $mock                = new Exception;
        $mock->className     = 'MuhMockException';
        $mock->code          = 'Dummy exception code';
        $mock->message       = <<<'s'
This is a dummy exception message.

lololool
s;
        $mock->previous      = null;
        $mock->stackMissing  = 8;
        $mock->localsMissing = 5;

        $mock->location       = new Location;
        $mock->location->file = '/path/to/muh/file';
        $mock->location->line = 9000;

        $mock->globals                          = new Globals;
        $mock->globals->staticPropertiesMissing = 1;
        $mock->globals->globalVariablesMissing  = 19;
        $mock->globals->staticVariablesMissing  = 7;

        $prop1            = new Property;
        $prop1->value     = $this->introspect(null);
        $prop1->name      = 'blahProperty';
        $prop1->access    = 'private';
        $prop1->className = 'BlahClass';
        $prop1->isDefault = false;

        $mock->globals->staticProperties = array($prop1);

        $static1               = new StaticVariable;
        $static1->name         = 'variable name';
        $static1->value        = $this->introspect(true);
        $static1->functionName = 'blahFunction';
        $static1->className    = null;

        $static2               = new StaticVariable;
        $static2->name         = 'lolStatic';
        $static2->value        = $this->introspect(null);
        $static2->functionName = 'blahMethod';
        $static2->className    = 'BlahAnotherClass';

        $mock->globals->staticVariables = array($static1, $static2);

        $global1        = new Variable;
        $global1->name  = '_SESSION';
        $global1->value = $this->introspect(true);

        $global2        = new Variable;
        $global2->name  = 'globalVariable';
        $global2->value = $this->introspect(-2734);

        $mock->globals->globalVariables = array($global1, $global2);

        $local1        = new Variable;
        $local1->name  = 'lol';
        $local1->value = $this->introspect(8);

        $local2        = new Variable;
        $local2->name  = 'foo';
        $local2->value = $this->introspect('bar');

        $mock->locals = array($local1, $local2);

        $stack1               = new Stack;
        $stack1->args         = array($this->introspect(new DummyClass1));
        $stack1->functionName = 'aFunction';
        $stack1->className    = 'DummyClass1';
        $stack1->isStatic     = false;
        $stack1->location     = clone $mock->location;
        $stack1->object       = $this->objectId(new DummyClass1);
        $stack1->argsMissing  = 3;

        $stack2               = new Stack;
        $stack2->args         = array($this->introspect(new DummyClass2));
        $stack2->functionName = 'aFunction';
        $stack2->className    = null;
        $stack2->isStatic     = null;
        $stack2->location     = clone $mock->location;
        $stack2->object       = null;
        $stack2->argsMissing  = 6;

        $mock->stack = array($stack1, $stack2);

        $value            = new Value;
        $value->type      = Type::EXCEPTION;
        $value->exception = $mock;
        return $value;
    }

    function introspectException(\Exception $e) {
        $result            = new Value;
        $result->type      = Type::EXCEPTION;
        $result->exception = $this->introspectException2($e);
        return $result;
    }

    private function introspectException2(\Exception $e = null, $includeGlobals = true) {
        if (!$e)
            return null;

        $locals = $e instanceof ExceptionHasLocalVariables ? $e->getLocalVariables() : null;
        $stack  = $e instanceof ExceptionHasFullTrace ? $e->getFullTrace() : $e->getTrace();

        $result            = new Exception;
        $result->className = get_class($e);
        $result->code      = $e->getCode();
        $result->message   = $e->getMessage();
        $result->location  = $this->introspectLocation($e->getFile(), $e->getLine());
        $result->globals   = $includeGlobals ? $this->introspectGlobals() : null;
        $result->locals    = $this->introspectVariables($locals);
        $result->stack     = $this->introspectStack($stack);
        $result->previous  = $this->introspectException2($e->getPrevious(), false);
        return $result;
    }

    private function introspectLocation($file, $line) {
        if (!$file)
            return null;
        $result         = new Location;
        $result->file   = $file;
        $result->line   = $line;
        $result->source = $this->introspectSourceCode($file, $line);
        return $result;
    }

    private function introspectGlobals() {
        $result                   = new Globals;
        $result->globalVariables  = $this->introspectVariables($GLOBALS);
        $result->staticProperties = $this->introspectStaticProperties();
        $result->staticVariables  = $this->introspectStaticVariables();
        return $result;
    }

    private function introspectStack(array $frames) {
        $results = array();
        foreach ($frames as $frame) {
            $function =& $frame['function'];
            $line     =& $frame['line'];
            $file     =& $frame['file'];
            $class    =& $frame['class'];
            $object   =& $frame['object'];
            $type     =& $frame['type'];
            $args     =& $frame['args'];

            $result               = new Stack;
            $result->functionName = $function;
            $result->location     = $this->introspectLocation($file, $line);
            $result->className    = $class;
            $result->object       = $this->objectId($object);

            if ($type === '::')
                $result->isStatic = true;
            else if ($type === '->')
                $result->isStatic = false;
            else
                $result->isStatic = null;

            if ($args !== null) {
                $result->args = array();
                foreach ($args as &$arg)
                    $result->args[] = $this->introspectRef($arg);
            }

            $results[] = $result;
        }

        return $results;
    }

    private function introspectSourceCode($file, $line) {
        if (!@is_readable($file))
            return null;

        $contents = @file_get_contents($file);

        if (!is_string($contents))
            return null;

        $lines   = explode("\n", $contents);
        $results = array();

        foreach (range($line - 10, $line + 10) as $line1) {
            if (isset($lines[$line1 - 1])) {
                $results[$line1] = $lines[$line1 - 1];
            }
        }

        return $results;
    }

    private function introspectStaticProperties() {
        $results = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                if ($property->class === $reflection->name) {
                    $results[] = $this->introspectProperty($property);
                }
            }
        }

        return $results;
    }

    private function introspectStaticVariables() {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                if ($method->class !== $reflection->name)
                    continue;

                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $name => &$value) {
                    $variable               = new StaticVariable;
                    $variable->name         = $name;
                    $variable->value        = $this->introspectRef($value);
                    $variable->className    = $method->class;
                    $variable->functionName = $method->getName();
                    $globals[]              = $variable;
                }
            }
        }

        foreach (get_defined_functions() as $section) {
            foreach ($section as $function) {
                $reflection      = new \ReflectionFunction($function);
                $staticVariables = $reflection->getStaticVariables();

                foreach ($staticVariables as $name => &$value2) {
                    $variable               = new StaticVariable;
                    $variable->name         = $name;
                    $variable->value        = $this->introspectRef($value2);
                    $variable->functionName = $function;
                    $globals[]              = $variable;
                }
            }
        }

        return $globals;
    }

    private function introspectVariables(array &$variables = null) {
        if (!is_array($variables))
            return null;
        /** @var Variable[] $results */
        $results = array();

        foreach ($variables as $name => &$value) {
            $result        = new Variable;
            $result->name  = $name;
            $result->value = $this->introspectRef($value);
            $results[]     = $result;
        }

        return $results;
    }

    private function objectId($object) {
        if (!$object)
            return null;
        $id =& $this->objectIds[spl_object_hash($object)];
        if ($id === null) {
            $id = count($this->objectIds);

            $this->root->objects[$id] = $this->introspectObject($object);
            return $id;
        }
        return $id;
    }

    function introspectRef(&$value) {
        $result = new Value;

        if (is_string($value)) {
            $result->type   = Type::STRING;
            $result->string = $this->stringId($value);
        } else if (is_int($value)) {
            $result->type = Type::INT;
            $result->int  = $value;
        } else if (is_bool($value)) {
            $result->type = $value ? Type::TRUE : Type::FALSE;
        } else if (is_null($value)) {
            $result->type = Type::NULL;
        } else if (is_float($value)) {
            if ($value === INF) {
                $result->type = Type::POS_INF;
            } else if ($value === -INF) {
                $result->type = Type::NEG_INF;
            } else if (is_nan($value)) {
                $result->type = Type::NAN;
            } else {
                $result->type  = Type::FLOAT;
                $result->float = $value;
            }
        } else if (is_array($value)) {
            $result->type  = Type::ARRAY1;
            $result->array = $this->arrayId($value);
        } else if (is_object($value)) {
            $result->type   = Type::OBJECT;
            $result->object = $this->objectId($value);
        } else if (is_resource($value)) {
            $result->type           = Type::RESOURCE;
            $result->resource       = new Resource1;
            $result->resource->id   = (int)$value;
            $result->resource->type = get_resource_type($value);
        } else {
            $result->type = Type::UNKNOWN;
        }

        return $result;
    }

    private function introspectProperty(\ReflectionProperty $property, $object = null) {
        $property->setAccessible(true);
        $result            = new Property;
        $result->className = $property->class;
        $result->name      = $property->name;
        $result->value     = $this->introspect($property->getValue($object));
        $result->isDefault = $property->isDefault();

        if ($property->isPrivate())
            $result->access = 'private';
        else if ($property->isProtected())
            $result->access = 'protected';
        else if ($property->isPublic())
            $result->access = 'public';
        else
            $result->access = null;

        return $result;
    }

    private function introspectObject($object) {
        $result             = new Object1;
        $result->className  = get_class($object);
        $result->hash       = spl_object_hash($object);
        $result->properties = $this->introspectObjectProperties($object);
        return $result;
    }

    private function stringId($value) {
        $id =& $this->stringIds[$value];
        if ($id === null) {
            $id = count($this->stringIds);

            $string               = new String1;
            $string->bytes        = $value;
            $string->bytesMissing = 0;

            $this->root->strings[$id] = $string;

            return $id;
        }
        return $id;
    }

    private function arrayId(array &$array) {
        foreach ($this->arrayIds as $id => &$array2) {
            if (self::refEqual($array2, $array)) {
                return $id;
            }
        }

        $id = count($this->arrayIds) + 1;

        $this->arrayIds[$id]     =& $array;
        $this->root->arrays[$id] = $this->introspectArray($array);

        return $id;
    }

    function introspect($value) {
        return $this->introspectRef($value);
    }

    private function introspectObjectProperties($object) {
        /** @var Property[] $results */
        $results = array();

        for ($reflection = new \ReflectionObject($object);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->class === $reflection->name) {
                    $results[] = $this->introspectProperty($property, $object);
                }
            }
        }

        return $results;
    }

    private static function refEqual(&$x, &$y) {
        $xOld   = $x;
        $x      = new \stdClass;
        $result = $x === $y;
        $x      = $xOld;

        return $result;
    }

    private function introspectArray(array $array) {
        $result                = new Array1;
        $result->isAssociative = self::isAssoc($array);

        foreach ($array as $key => &$value) {
            $entry             = new ArrayEntry;
            $entry->key        = $this->introspectRef($key);
            $entry->value      = $this->introspectRef($value);
            $result->entries[] = $entry;
        }

        return $result;
    }

    function root(Value $value) {
        $root       = clone $this->root;
        $root->root = $value;
        return $root;
    }

    private static function isAssoc(array $array) {
        $i = 0;
        foreach ($array as $k => $v)
            if ($k !== $i++)
                return true;
        return false;
    }
}

class String1 extends Base {
    /** @var string */
    public $bytes;
    /** @var int */
    public $bytesMissing;
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
    /** @var Value */
    public $key;
    /** @var Value */
    public $value;

    function pushJson(array $json) {
        parent::pushJson($json);
        Value::json($this->key);
        Value::json($this->value);
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
    /** @var Value */
    public $value;

    function pushJson(array $json) {
        parent::pushJson($json);
        Value::json($this->value);
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
    public $staticPropertiesMissing;
    /** @var StaticVariable[] */
    public $staticVariables;
    /** @var int */
    public $staticVariablesMissing;
    /** @var Variable[] */
    public $globalVariables;
    /** @var int */
    public $globalVariablesMissing;

    function pushJson(array $json) {
        parent::pushJson($json);
        Property::jsons($this->staticProperties);
        StaticVariable::jsons($this->staticVariables);
        Variable::jsons($this->globalVariables);
    }
}

class Exception extends Base {
    /** @var Variable[] */
    public $locals;
    /** @var int */
    public $localsMissing = 0;
    /** @var Globals */
    public $globals;
    /** @var Stack[] */
    public $stack;
    /** @var int */
    public $stackMissing;
    /** @var string */
    public $className;
    /** @var string */
    public $code;
    /** @var string */
    public $message;
    /** @var Location */
    public $location;
    /** @var Exception */
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
    /** @var Value[] */
    public $args;
    /** @var int */
    public $argsMissing;
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
        Value::jsons($this->args);
        Location::json($this->location);
    }
}

class Value extends Base {
    /** @var string */
    public $type;
    /** @var Exception */
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
        Exception::json($this->exception);
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

