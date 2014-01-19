<?php

namespace ErrorHandler;

class Introspection {
    function introspect($x) { return self::introspectRef($x); }

    function introspectRef(&$x) {
        if (is_string($x))
            return new ValueString($x);
        else if (is_int($x))
            return new ValueInt($x);
        else if (is_bool($x))
            return new ValueBool($x);
        else if (is_null($x))
            return new ValueNull;
        else if (is_float($x))
            return new ValueFloat($x);
        else if (is_array($x))
            return $this->introspectArray($x);
        else if (is_object($x))
            return $this->introspectObject($x);
        else if (is_resource($x))
            return $this->introspectResource($x);
        else
            return new ValueUnknown;
    }

    function introspectException(\Exception $e) {
        $result = $this->introspectImplNoGlobals($e);
        $result->setGlobals($this->introspectGlobals());

        return $result;
    }

    function mockException() { return ValueException::mock($this); }

    /** @var ValueObject[] */
    private $objectCache = array();
    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    private $objects = array();
    /** @var IntrospectionArrayCacheEntry[] */
    private $arrayCache = array();

    private function introspectArray(array &$x) {
        foreach ($this->arrayCache as $entry)
            if (ref_equal($entry->array, $x))
                return $entry->result;

        $result = new ValueArray;

        $entry              = new IntrospectionArrayCacheEntry;
        $entry->array       =& $x;
        $entry->result      = $result;
        $this->arrayCache[] = $entry;

        $result->setIsAssociative(array_is_associative($x));

        foreach (ref_new($x) as $k => &$v)
            $result->addEntry($this->introspect($k), $this->introspectRef($v));

        return $result;
    }

    private function introspectObject($x) {
        $hash   = spl_object_hash($x);
        $result =& $this->objectCache[$hash];

        if ($result !== null)
            return $result;

        $this->objects[] = $x;

        $result = new ValueObject;
        $result->setHash($hash);
        $result->setClass(get_class($x));

        for ($reflection = new \ReflectionObject($x);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->class === $reflection->name) {
                    $result->addProperty($this->introspectObjectProperty($property, $x, new ValueObjectProperty));
                }
            }
        }

        return $result;
    }

    private function introspectObjectProperty(\ReflectionProperty $p, $object = null, ValueObjectProperty $result) {
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

    private function introspectResource($x) {
        $result = new ValueResource;
        $result->setId((int)$x);
        $result->setType(get_resource_type($x));

        return $result;
    }

    /**
     * @param \Exception $e
     *
     * @return ValueException|null
     */
    private function introspectImplNoGlobals(\Exception $e = null) {
        if ($e === null)
            return null;

        $locals = $e instanceof ExceptionHasLocalVariables ? $e->getLocalVariables() : null;
        $frames = $e instanceof ExceptionHasFullTrace ? $e->getFullTrace() : $e->getTrace();

        $result = new ValueException;
        $result->setClass(get_class($e));
        $result->setCode($e->getCode());
        $result->setMessage($e->getMessage());
        $result->setLocation($this->introspectCodeLocation($e->getFile(), $e->getLine()));
        $result->setLocals($this->introspectLocals($locals));
        $result->setStack($this->introspectStack($frames));
        $result->setPrevious($this->introspectImplNoGlobals($e->getPrevious()));

        return $result;
    }

    private function introspectGlobals() {
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
                $globals[] = $this->introspectObjectProperty($property, new ValueObjectPropertyStatic);
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
                    $v = new ValueVariableStatic;
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
                    $v = new ValueVariableStatic;
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

    private function introspectCodeLocation($file, $line) {
        if ($file === null)
            return null;

        $result = new ValueExceptionCodeLocation;
        $result->setFile($file);
        $result->setLine($line);
        $result->setSourceCode($this->introspectSourceCode($file, $line));

        return $result;
    }

    private function introspectLocals($locals) {
        if ($locals === null)
            return null;

        $result = array();
        foreach ($locals as $key => &$value) {
            $variable = new ValueVariable;
            $variable->setName($key);
            $variable->setValue($this->introspectRef($value));
            $result[] = $variable;
        }

        return $result;
    }

    private function introspectStack($frames) {
        $result = array();

        foreach ($frames as $frame) {
            $stackFrame = new ValueExceptionStackFrame;
            $stackFrame->setFunction(array_get($frame, 'function'));
            $stackFrame->setLocation($this->introspectCodeLocation(array_get($frame, 'file'), array_get($frame, 'line')));
            $stackFrame->setClass(array_get($frame, 'class'));
            $stackFrame->setIsStatic(isset($frame['type']) ? $frame['type'] === '::' : null);
            $stackFrame->setObject(isset($frame['object']) ? $this->introspectObject($frame['object']) : null);

            if (isset($frame['args'])) {
                $args = array();

                foreach ($frame['args'] as &$arg)
                    $args[] = $this->introspectRef($arg);

                $stackFrame->setArgs($args);
            }

            $result[] = $stackFrame;
        }

        return $result;
    }
}

class IntrospectionArrayCacheEntry {
    /** @var array */
    public $array;
    /** @var ValueArray */
    public $result;
}
