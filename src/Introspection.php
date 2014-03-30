<?php

namespace ErrorHandler;

class Introspection {
    function introspect($x) {
        return new IntrospectionValue($this, $x);
    }

    function introspectRef(&$x) {
        if (is_array($x)) {
            return new IntrospectionArray($this, $x, $this->arrayID($x));
        } else {
            return $this->introspect($x);
        }
    }

    function introspectException(\Exception $e) {
        return new IntrospectionException($this, $e);
    }

    private $nextObjectID = 1;
    private $nextArrayID = 1;
    private $nextStringID = 1;
    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    private $objects = array();
    private $arrayIDs = array();
    private $objectIDs = array();
    private $stringIDs = array();

    function introspectAcceptVisitor($value, ValueVisitor $visitor) {
        if (is_string($value))
            return $visitor->visitString(new IntrospectionString($this->stringID($value), $value));
        else if (is_int($value))
            return $visitor->visitInt($value);
        else if (is_bool($value))
            return $visitor->visitBool($value);
        else if (is_null($value))
            return $visitor->visitNull();
        else if (is_float($value))
            return $visitor->visitFloat($value);
        else if (is_array($value))
            return $visitor->visitArray(new IntrospectionArray($this, $value, $this->nextArrayID++));
        else if (is_object($value))
            return $visitor->visitObject($this->introspectObject($value));
        else if (is_resource($value))
            return $visitor->visitResource(new IntrospectionResource($value));
        else
            return $visitor->visitUnknown();
    }

    private function stringID($string) {
        $id =& $this->stringIDs[$string];
        if ($id === null)
            $id = $this->nextStringID++;

        return $id;
    }

    private function arrayID(array &$array) {
        foreach ($this->arrayIDs as $id => &$array2) {
            if (self::refEqual($array2, $array)) {
                return $id;
            }
        }

        $id = $this->nextArrayID++;

        $this->arrayIDs[$id] =& $array;

        return $id;
    }

    function introspectObject($object) {
        $id =& $this->objectIDs[spl_object_hash($object)];
        if ($id === null) {
            $id = $this->nextObjectID++;

            $this->objects[] = $object;
        }

        return new IntrospectionObject($this, $object, $id);
    }

    private static function refEqual(&$x, &$y) {
        $xOld   = $x;
        $x      = new \stdClass;
        $result = $x === $y;
        $x      = $xOld;

        return $result;
    }
}

class IntrospectionString implements ValueString {
    private $id, $string;

    function __construct($id, $string) {
        $this->id     = $id;
        $this->string = $string;
    }

    function id() { return $this->id; }

    function bytes() { return $this->string; }

    function bytesMissing() { return 0; }
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
            if (isset($lines[$lineToScan - 1])) {
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
    private $id;
    private $properties;

    function __construct(Introspection $introspection, $object, $id) {
        $this->properties    = IntrospectionObjectProperty::objectProperties($introspection, $object);
        $this->introspection = $introspection;
        $this->object        = $object;
        $this->id            = $id;
    }

    function className() { return get_class($this->object); }

    function properties() { return $this->properties; }

    function hash() { return spl_object_hash($this->object); }

    function id() { return $this->id; }

    function propertiesMissing() { return 0; }
}

class IntrospectionArray implements ValueArray, ValueImpl {
    private $introspection;
    private $array;
    private $id;

    function __construct(Introspection $introspection, array $array, $id) {
        $this->introspection = $introspection;
        $this->array         = $array;
        $this->id            = $id;
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

    function id() { return $this->id; }

    function entries() { return IntrospectionArrayEntry::introspect($this->introspection, $this->array); }

    function acceptVisitor(ValueVisitor $visitor) {
        return $visitor->visitArray($this);
    }

    function entriesMissing() { return 0; }
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

class IntrospectionException implements ValueException, ValueImpl {
    private $introspection;
    private $exception;
    private $includeGlobals;
    private $stack = array();
    private $locals;

    function __construct(Introspection $introspection, \Exception $exception, $includeGlobals = true) {
        $frames = $exception instanceof ExceptionHasFullTrace ? $exception->getFullTrace() : $exception->getTrace();
        $locals = $exception instanceof ExceptionHasLocalVariables ? $exception->getLocalVariables() : null;

        $this->introspection  = $introspection;
        $this->exception      = $exception;
        $this->includeGlobals = $includeGlobals;
        $this->locals         = is_array($locals) ? IntrospectionVariable::introspect($introspection, $locals) : null;

        foreach ($frames as $frame)
            $this->stack[] = new IntrospectionStackFrame($introspection, $frame);
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
        return $this->includeGlobals ? new IntrospectionGlobals($this->introspection) : null;
    }

    function locals() { return $this->locals; }

    function localsMissing() {
        return 0;
    }

    function stack() { return $this->stack; }

    function acceptVisitor(ValueVisitor $visitor) {
        return $visitor->visitException($this);
    }

    function stackMissing() { return 0; }
}

class IntrospectionGlobals implements ValueGlobals {
    private $introspection;
    private $staticProperties;
    private $staticVariables;

    function __construct(Introspection $introspection) {
        $this->staticVariables  = IntrospectionStaticVariable::all($introspection);
        $this->staticProperties = IntrospectionObjectProperty::staticProperties($introspection);
        $this->introspection    = $introspection;
    }

    function staticProperties() { return $this->staticProperties; }

    function staticVariables() { return $this->staticVariables; }

    function globalVariables() { return IntrospectionVariable::introspect($this->introspection, $GLOBALS); }

    function staticPropertiesMissing() { return 0; }

    function staticVariablesMissing() { return 0; }

    function globalVariablesMissing() { return 0; }
}

class IntrospectionVariable implements ValueVariable {
    static function introspect(Introspection $introspection, array &$variables) {
        $results = array();

        foreach ($variables as $name => &$value) {
            $results[] = new self($name, $introspection->introspectRef($value));
        }

        return $results;
    }

    private function __construct($name, ValueImpl $value) {
        $this->name  = $name;
        $this->value = $value;
    }

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
                if ($property->class === $reflection->name) {
                    $self                = new self;
                    $self->introspection = $introspection;
                    $self->property      = $property;
                    $results[]           = $self;
                }
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
                if ($method->class !== $reflection->name)
                    continue;

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
        $function = $this->key('functionName');

        return is_scalar($function) ? "$function" : null;
    }

    function location() {
        return IntrospectionCodeLocation::create($this->key('file'), $this->key('line'));
    }

    function className() {
        $class = $this->key('className');

        return is_scalar($class) ? "$class" : null;
    }

    function isStatic() {
        $type = $this->key('type');

        return $type === '::' ? true : ($type === '->' ? false : null);
    }

    function object() {
        $object = $this->key('object');

        return is_object($object) ? $this->introspection->introspectObject($object) : null;
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

    function argumentsMissing() {
        return 0;
    }
}

class IntrospectionValue implements ValueImpl {
    private $introspection;
    private $value;

    function __construct(Introspection $introspection, $value) {
        $this->introspection = $introspection;
        $this->value         = $value;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        return $this->introspection->introspectAcceptVisitor($this->value, $visitor);
    }
}

