<?php

namespace ErrorHandler;

class Introspection {
    function introspect($x) { return $this->introspectRef($x); }

    function introspectRef(&$x) { return new IntrospectionValue($x, $this); }

    function introspectException(\Exception $e) { return new IntrospectionException($this, $e); }

    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    private $objects = array();
    private $arrayIDs = array();
    private $objectIDs = array();

    function arrayID(array &$array) {
        foreach ($this->arrayIDs as $id => &$array2) {
            if (self::refEqual($array2, $array)) {
                return $id;
            }
        }

        $id = count($this->arrayIDs);

        $this->arrayIDs[$id] =& $array;

        return $id;
    }

    function objectID($object) {
        $id =& $this->objectIDs[spl_object_hash($object)];
        if ($id === null) {
            $id = count($this->objectIDs) - 1;

            $this->objects[] = $object;
        }

        return $id;
    }

    private static function refEqual(&$x, &$y) {
        $xOld   = $x;
        $x      = new \stdClass;
        $result = $x === $y;
        $x      = $xOld;

        return $result;
    }
}

class IntrospectionCodeLocation implements ValueCodeLocation {
    static function create($file, $line) {
        if (is_scalar($file) && is_scalar($line)) {
            $self       = new self;
            $self->file = "$file";
            $self->line = (int)$line;

            return $self;
        } else {
            return null;
        }
    }

    private function __construct() { }

    private $file;
    private $line;

    function line() { return $this->line; }

    function file() { return $this->file; }

    function sourceCode() {
        $contents = @file_get_contents($this->file);

        if (!is_string($contents))
            return null;

        $lines   = explode("\n", $contents);
        $results = array();

        foreach (range($this->line - 5, $this->line + 5) as $lineToScan) {
            if (isset($lines[$lineToScan])) {
                $results[$lineToScan] = $lines[$lineToScan - 1];
            }
        }

        return $results;
    }
}

class IntrospectionResource implements ValueResource {
    private $resource;

    function __construct($resource) {
        $this->resource = $resource;
    }

    function type() { return get_resource_type($this->resource); }

    function id() { return (int)$this->resource; }
}

class IntrospectionObject implements ValueObject {
    private $introspection;
    private $object;

    function __construct(Introspection $introspection, $object) {
        $this->introspection = $introspection;
        $this->object        = $object;
    }

    function className() { return get_class($this->object); }

    function properties() {
        return IntrospectionObjectProperty::objectProperties($this->introspection, $this->object);
    }

    function hash() { return spl_object_hash($this->object); }

    function id() { return $this->introspection->objectID($this->object); }
}

class IntrospectionArray implements ValueArray {
    private $introspection;
    private $array;

    function __construct(Introspection $introspection, array &$array) {
        $this->introspection = $introspection;
        $this->array         =& $array;
    }

    function isAssociative() {
        $i = 0;

        foreach ($this->array as $k => $v) {
            if ($k !== $i++) {
                return true;
            }
        }

        return false;
    }

    function id() { return $this->introspection->arrayID($this->array); }

    function entries() { return IntrospectionArrayEntry::introspect($this->introspection, $this->array); }
}

class IntrospectionArrayEntry implements ValueArrayEntry {
    static function introspect(Introspection $introspection, array &$array) {
        $entries = array();

        foreach ($array as $key => &$value) {
            $entry        = new self;
            $entry->key   = $introspection->introspect($key);
            $entry->value = $introspection->introspectRef($value);
            $entries[]    = $entry;
        }

        return $entries;
    }

    private $key;
    private $value;

    private function __construct() { }

    function key() { return $this->key; }

    function value() { return $this->value; }
}

class IntrospectionException implements ValueException, Value {
    private $introspection;
    private $exception;
    private $includeGlobals;

    function __construct(Introspection $introspection, \Exception $exception, $includeGlobals = true) {
        $this->introspection  = $introspection;
        $this->exception      = $exception;
        $this->includeGlobals = $includeGlobals;
    }

    function className() { return get_class($this->exception); }

    function code() {
        $code = $this->exception->getCode();

        return is_scalar($code) ? "$code" : '';
    }

    function message() {
        $message = $this->exception->getMessage();

        return is_scalar($message) ? "$message" : '';
    }

    function previous() {
        $previous = $this->exception->getPrevious();

        return $previous instanceof \Exception ? new self($this->introspection, $previous, false) : null;
    }

    function location() {
        $file = $this->exception->getFile();
        $line = $this->exception->getLine();

        return IntrospectionCodeLocation::create($file, $line);
    }

    function globals() {
        if (!$this->includeGlobals)
            return null;

        return new IntrospectionGlobals($this->introspection);
    }

    function locals() {
        $locals = $this->exception instanceof ExceptionHasLocalVariables ? $this->exception->getLocalVariables() : null;

        return is_array($locals) ? IntrospectionVariable::introspect($this->introspection, $locals) : null;
    }

    function stack() {
        $frames = $this->exception instanceof ExceptionHasFullTrace
            ? $this->exception->getFullTrace()
            : $this->exception->getTrace();

        if (!is_array($frames))
            return array();

        $result = array();

        foreach ($frames as $frame) {
            $frame    = is_array($frame) ? $frame : array();
            $result[] = new IntrospectionStackFrame($this->introspection, $frame);
        }

        return $result;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        return $visitor->visitException($this);
    }
}

class IntrospectionGlobals implements ValueGlobals {
    private $introspection;

    function __construct(Introspection $introspection) {
        $this->introspection = $introspection;
    }

    function staticProperties() { return IntrospectionObjectProperty::staticProperties($this->introspection); }

    function staticVariables() { return IntrospectionStaticVariable::all($this->introspection); }

    function globalVariables() { return IntrospectionVariable::introspect($this->introspection, $GLOBALS); }
}

class IntrospectionVariable implements ValueVariable {
    static function introspect(Introspection $introspection, array &$variables) {
        $results = array();

        foreach ($variables as $name => &$value) {
            $self        = new self;
            $self->name  = $name;
            $self->value = $introspection->introspectRef($value);
            $results[]   = $self;
        }

        return $results;
    }

    private function __construct() { }

    private $name;
    private $value;

    function name() { return $this->name; }

    function value() { return $this->value; }
}

class IntrospectionObjectProperty implements ValueObjectProperty {
    static function staticProperties(Introspection $introspection) {
        $results = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $self                = new self;
                $self->introspection = $introspection;
                $self->property      = $property;
                $results[]           = $self;
            }
        }

        return $results;
    }

    static function objectProperties(Introspection $introspection, $object) {
        $results = array();

        for ($reflection = new \ReflectionObject($object);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->class === $reflection->name) {
                    $self                = new self;
                    $self->introspection = $introspection;
                    $self->property      = $property;
                    $self->object        = $object;
                    $results[]           = $self;
                }
            }
        }

        return $results;
    }

    /** @var Introspection */
    private $introspection;
    /** @var \ReflectionProperty */
    private $property;
    /** @var object */
    private $object;

    private function __construct() { }

    function name() { return $this->property->name; }

    function value() {
        $this->property->setAccessible(true);

        return $this->introspection->introspect($this->property->getValue($this->object));
    }

    function access() {
        if ($this->property->isPrivate())
            return 'private';
        else if ($this->property->isProtected())
            return 'protected';
        else
            return 'public';
    }

    function className() { return $this->property->class; }

    function isDefault() { return $this->property->isDefault(); }
}

class IntrospectionStaticVariable implements ValueStaticVariable {
    static function all(Introspection $i) {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $name => &$value) {
                    $variable           = new self;
                    $variable->name     = $name;
                    $variable->value    = $i->introspectRef($value);
                    $variable->class    = $method->class;
                    $variable->function = $method->getName();
                    $globals[]          = $variable;
                }
            }
        }

        foreach (get_defined_functions() as $section) {
            foreach ($section as $function) {
                $reflection      = new \ReflectionFunction($function);
                $staticVariables = $reflection->getStaticVariables();

                foreach ($staticVariables as $name => &$value2) {
                    $variable           = new self;
                    $variable->name     = $name;
                    $variable->value    = $i->introspectRef($value2);
                    $variable->function = $function;
                    $globals[]          = $variable;
                }
            }
        }

        return $globals;
    }

    private $function;
    private $name;
    private $value;
    private $class;

    private function __construct() { }

    function name() { return $this->name; }

    function value() { return $this->value; }

    function functionName() { return $this->function; }

    function className() { return $this->class; }
}

class IntrospectionStackFrame implements ValueStackFrame {
    private $introspection;
    private $frame;

    function __construct(Introspection $introspection, array $frame) {
        $this->introspection = $introspection;
        $this->frame         = $frame;
    }

    function functionName() {
        $function = $this->key('function');

        return is_scalar($function) ? "$function" : null;
    }

    function location() {
        return IntrospectionCodeLocation::create($this->key('file'),
                                                 $this->key('line'));
    }

    function className() {
        $class = $this->key('class');

        return is_scalar($class) ? "$class" : null;
    }

    function isStatic() {
        $type = $this->key('type');

        return $type === '::' ? true : ($type === '->' ? false : null);
    }

    function object() {
        $object = $this->key('object');

        return is_object($object) ? new IntrospectionObject($this->introspection, $object) : null;
    }

    function arguments() {
        $args = $this->key('args');

        if (is_array($args)) {
            $result = array();

            foreach ($args as &$arg) {
                $result[] = $this->introspection->introspectRef($arg);
            }

            return $result;
        } else {
            return null;
        }
    }

    private function key($key) {
        return isset($this->frame[$key]) ? $this->frame[$key] : null;
    }
}

class IntrospectionValue implements Value {
    private $value;
    private $introspection;

    function __construct(&$value, Introspection $introspection) {
        $this->value         =& $value;
        $this->introspection = $introspection;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        $value =& $this->value;

        if (is_string($value))
            return $visitor->visitString($value);
        else if (is_int($value))
            return $visitor->visitInt($value);
        else if (is_bool($value))
            return $visitor->visitBool($value);
        else if (is_null($value))
            return $visitor->visitNull();
        else if (is_float($value))
            return $visitor->visitFloat($value);
        else if (is_array($value))
            return $visitor->visitArray(new IntrospectionArray($this->introspection, $value));
        else if (is_object($value))
            return $visitor->visitObject(new IntrospectionObject($this->introspection, $value));
        else if (is_resource($value))
            return $visitor->visitResource(new IntrospectionResource($value));
        else
            return $visitor->visitUnknown();
    }
}

