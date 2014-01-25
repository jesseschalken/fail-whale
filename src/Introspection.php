<?php

namespace ErrorHandler;

class Introspection {
    function introspect($x) { return self::introspectRef($x); }

    function introspectRef(&$x) {
        return new IntrospectionValue($x, $this);
    }

    function introspectException(\Exception $e) {
        return new IntrospectionException($this, $e);
    }

    function mockException() { return MutableValueException::mock($this); }

    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    private $objects = array();
    private $arrayIDs = array();
    private $objectIDs = array();

    function arrayID(array &$array) {
        foreach ($this->arrayIDs as $id => &$array2) {
            if (ref_equal($array2, $array)) {
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

    function introspectArray(array &$x) {
        $result = new MutableValueArray;
        $result->setID($this->arrayID($x));
        $result->setIsAssociative(array_is_associative($x));

        foreach ($x as $k => &$v) {
            $result->addEntry($this->introspect($k), $this->introspectRef($v));
        }

        return $result;
    }

    function introspectObject($x) {
        $result = new MutableValueObject;
        $result->setId($this->objectID($x));
        $result->setHash(spl_object_hash($x));
        $result->setClass(get_class($x));

        for ($reflection = new \ReflectionObject($x);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->class === $reflection->name) {
                    $result->addProperty($this->introspectObjectProperty($property, $x, new MutableValueObjectProperty));
                }
            }
        }

        return $result;
    }

    private function introspectObjectProperty(\ReflectionProperty $p, $object = null, MutableValueObjectProperty $result) {
        $p->setAccessible(true);

        $result->setName($p->name);
        $result->setValue($this->introspect($p->getValue($object)));
        $result->setClass($p->class);
        $result->setAccess($this->accessAsString($p));
        $result->setIsDefault($p->isDefault());

        return $result;
    }

    private function accessAsString(\ReflectionProperty $property) {
        if ($property->isPublic())
            return 'public';
        else if ($property->isPrivate())
            return 'private';
        else if ($property->isProtected())
            return 'protected';
        else
            throw new \Exception("This thing is not protected, public, nor private? Huh?");
    }

    function introspectResource($x) {
        return new ValueResource(get_resource_type($x), (int)$x);
    }

    function introspectGlobals() {
        $result = new ValueExceptionGlobalState;
        $result->setStaticProperties($this->introspectStaticProperties());
        $result->setGlobalVariables($this->introspectGlobalVariables());
        $result->setStaticVariables($this->introspectStaticVariables());

        return $result;
    }

    private function introspectStaticProperties() {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $globals[] = $this->introspectObjectProperty($property, null, new ValueObjectPropertyStatic);
            }
        }

        return $globals;
    }

    private function introspectGlobalVariables() {
        $globals = array();

        foreach ($GLOBALS as $variableName => &$globalValue) {
            if ($variableName !== 'GLOBALS') {
                $variable = new ValueGlobalVariable;
                $variable->setName($variableName);
                $variable->setValue($this->introspectRef($globalValue));
                $globals [] = $variable;
            }
        }

        return $globals;
    }

    private function introspectStaticVariables() {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $variableName => &$varValue) {
                    $v = new MutableValueVariableStatic;
                    $v->setName($variableName);
                    $v->setValue($this->introspectRef($varValue));
                    $v->setClass($method->class);
                    $v->setFunction($method->getName());

                    $globals[] = $v;
                }
            }
        }

        foreach (get_defined_functions() as $section) {
            foreach ($section as $function) {
                $reflection      = new \ReflectionFunction($function);
                $staticVariables = $reflection->getStaticVariables();

                foreach ($staticVariables as $propertyName => &$varValue) {
                    $v = new MutableValueVariableStatic;
                    $v->setName($propertyName);
                    $v->setValue($this->introspectRef($varValue));
                    $v->setFunction($function);

                    $globals[] = $v;
                }
            }
        }

        return $globals;
    }

    private function introspectSourceCode($file, $line) {
        if ($file === null)
            return null;

        $contents = @file_get_contents($file);

        if (!is_string($contents))
            return null;

        $lines   = explode("\n", $contents);
        $results = array();

        foreach (range($line - 5, $line + 5) as $lineToScan) {
            if (isset($lines[$lineToScan])) {
                $results[$lineToScan] = $lines[$lineToScan - 1];
            }
        }

        return $results;
    }

    function introspectCodeLocation($file, $line) {
        if (is_scalar($file) && is_scalar($line)) {
            $result = new ValueExceptionCodeLocation;
            $result->setFile("$file");
            $result->setLine((int)$line);
            $result->setSourceCode($this->introspectSourceCode($file, $line));

            return $result;
        } else {
            return null;
        }
    }
}

class IntrospectionException implements ValueException, Value {
    private $i;
    private $e;
    private $includeGlobals;

    function __construct(Introspection $i, \Exception $e = null, $includeGlobals = true) {
        $this->i = $i;
        $this->e = $e;

        $this->includeGlobals = $includeGlobals;
    }

    function className() { return get_class($this->e); }

    function code() {
        $code = $this->e->getCode();

        return is_scalar($code) ? "$code" : '';
    }

    function message() {
        $message = $this->e->getMessage();

        return is_scalar($message) ? "$message" : '';
    }

    function previous() {
        $previous = $this->e->getPrevious();

        return $previous instanceof \Exception ? new self($this->i, $previous, false) : null;
    }

    function location() {
        $file = $this->e->getFile();
        $line = $this->e->getLine();

        return $this->i->introspectCodeLocation($file, $line);
    }

    function globals() {
        if (!$this->includeGlobals)
            return null;

        return $this->i->introspectGlobals();
    }

    function locals() {
        $locals = $this->e instanceof ExceptionHasLocalVariables ? $this->e->getLocalVariables() : null;

        if (!is_array($locals))
            return null;

        $result = array();
        foreach ($locals as $key => &$value) {
            $variable = new MutableValueVariable;
            $variable->setName($key);
            $variable->setValue($this->i->introspectRef($value));
            $result[] = $variable;
        }

        return $result;
    }

    function stack() {
        $frames = $this->e instanceof ExceptionHasFullTrace ? $this->e->getFullTrace() : $this->e->getTrace();

        if (!is_array($frames))
            return array();

        $result = array();

        foreach ($frames as $frame) {
            $frame    = is_array($frame) ? $frame : array();
            $result[] = new IntrospectionStackFrame($this->i, $frame);
        }

        return $result;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        return $visitor->visitException($this);
    }
}

class IntrospectionStackFrame implements ValueExceptionStackFrame {
    private $i;
    private $frame;

    function __construct(Introspection $i, array $frame) {
        $this->i     = $i;
        $this->frame = $frame;
    }

    function getFunction() {
        $function = array_get($this->frame, 'function');

        return is_scalar($function) ? "$function" : null;
    }

    function getLocation() {
        $file = array_get($this->frame, 'file');
        $line = array_get($this->frame, 'line');

        return $this->i->introspectCodeLocation($file, $line);
    }

    function getClass() {
        $class = array_get($this->frame, 'class');

        return is_scalar($class) ? "$class" : null;
    }

    function getIsStatic() {
        $type = array_get($this->frame, 'type');

        return $type === '::' ? true : ($type === '->' ? false : null);
    }

    function getObject() {
        $object = array_get($this->frame, 'object');

        return is_object($object) ? $this->i->introspectObject($object) : null;
    }

    function getArgs() {
        $args = array_get($this->frame, 'args');

        if (is_array($args)) {
            $result = array();

            foreach ($args as &$arg) {
                $result[] = $this->i->introspectRef($arg);
            }

            return $result;
        } else {
            return null;
        }
    }
}

class IntrospectionValue implements Value {
    private $x;
    private $i;

    function __construct(&$x, Introspection $i) {
        $this->x =& $x;
        $this->i = $i;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        $x =& $this->x;

        if (is_string($x))
            return $visitor->visitString(new ValueString($x));
        else if (is_int($x))
            return $visitor->visitInt($x);
        else if (is_bool($x))
            return $visitor->visitBool($x);
        else if (is_null($x))
            return $visitor->visitNull();
        else if (is_float($x))
            return $visitor->visitFloat($x);
        else if (is_array($x))
            return $visitor->visitArray($this->i->introspectArray($x));
        else if (is_object($x))
            return $visitor->visitObject($this->i->introspectObject($x));
        else if (is_resource($x))
            return $visitor->visitResource($this->i->introspectResource($x));
        else
            return $visitor->visitUnknown();
    }
}

class IntrospectionArrayCacheEntry {
    /** @var array */
    public $array;
    /** @var MutableValueArray */
    public $result;
}
