<?php

namespace FailWhale;

class IntrospectionSettings {
    public $maxArrayEntries = 1000;
    public $maxObjectProperties = 100;
    public $maxStringLength = 10000;
    public $maxStackFrames = 100;
    public $maxLocalVariables = 100;
    public $maxStaticProperties = 100;
    public $maxStaticVariables = 100;
    public $maxGlobalVariables = 100;
    public $maxFunctionArguments = 100;
    public $maxSourceCodeContext = 10;
    public $includeSourceCode = true;
    /**
     * This prefix will be removed from the start of all file paths if present.
     *
     * @var string
     */
    public $fileNamePrefix = '';
    /**
     * This prefix will be removed from the start of all names of classes and functions.
     *
     * @var string
     */
    public $namespacePrefix = '\\';
}

class Introspection {
    /** @var Root */
    private $root;
    private $stringIds = array();
    private $objectIds = array();
    private $arrayIdRefs = array();
    private $nextArrayId = 1;
    private $limits;

    function __construct(IntrospectionSettings $limits = null) {
        $this->root   = new Root;
        $this->limits = $limits ? : new IntrospectionSettings;
    }

    function mockException() {
        $mock                = new ExceptionImpl;
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

        $arg1              = new FunctionArg;
        $arg1->name        = 'arg1';
        $arg1->value       = $this->introspect(new DummyClass1);
        $arg1->typeHint    = get_class(new DummyClass1);
        $arg1->isReference = false;

        $stack1               = new Stack;
        $stack1->args         = array($arg1);
        $stack1->functionName = 'aFunction';
        $stack1->className    = 'DummyClass1';
        $stack1->isStatic     = false;
        $stack1->location     = clone $mock->location;
        $stack1->object       = $this->objectId(new DummyClass1);
        $stack1->argsMissing  = 3;

        $arg2              = new FunctionArg;
        $arg2->name        = 'anArray';
        $arg2->value       = $this->introspect(new DummyClass2);
        $arg2->isReference = true;
        $arg2->typeHint    = 'array';

        $stack2               = new Stack;
        $stack2->args         = array($arg2);
        $stack2->functionName = 'aFunction';
        $stack2->className    = null;
        $stack2->isStatic     = null;
        $stack2->location     = clone $mock->location;
        $stack2->object       = null;
        $stack2->argsMissing  = 6;

        $mock->stack = array($stack1, $stack2);

        $value            = new ValueImpl;
        $value->type      = Type::EXCEPTION;
        $value->exception = $mock;
        return $value;
    }

    function introspect($value) {
        $result = new ValueImpl;

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

    private function stringId($value) {
        $id =& $this->stringIds[$value];
        if ($id === null) {
            $id = count($this->stringIds);

            $string               = new String1;
            $string->bytes        = (string)substr($value, 0, $this->limits->maxStringLength);
            $string->bytesMissing = strlen($value) - strlen($string->bytes);

            $this->root->strings[$id] = $string;

            return $id;
        }
        return $id;
    }

    private function arrayId(array $value) {
        $id = $this->nextArrayId++;

        $this->root->arrays[$id] = $this->introspectArray($value);
        return $id;
    }

    private function introspectObject($object) {
        $result             = new Object1;
        $result->className  = $this->removeNamespacePrefix(get_class($object));
        $result->hash       = spl_object_hash($object);
        $result->properties = $this->introspectObjectProperties($object, $result->propertiesMissing);
        return $result;
    }

    private function removeNamespacePrefix($name) {
        $name   = "\\$name";
        $prefix = $this->limits->namespacePrefix;

        if (substr($name, 0, strlen($prefix)) === $prefix)
            return (string)substr($name, strlen($prefix));
        else
            return $name;
    }

    private function introspectArray(array $array) {
        $result                = new Array1;
        $result->isAssociative = self::isAssoc($array);

        foreach ($array as $key => &$value) {
            if (count($result->entries) >= $this->limits->maxArrayEntries) {
                $result->entriesMissing++;
            } else {
                $entry             = new ArrayEntry;
                $entry->key        = $this->introspect($key);
                $entry->value      = $this->introspectRef($value);
                $result->entries[] = $entry;
            }
        }

        return $result;
    }

    private function introspectObjectProperties($object, &$missing) {
        /** @var Property[] $results */
        $results = array();

        for ($reflection = new \ReflectionObject($object);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->class === $reflection->name) {
                    if (count($results) >= $this->limits->maxObjectProperties)
                        $missing++;
                    else
                        $results[] = $this->introspectProperty($property, $object);
                }
            }
        }

        return $results;
    }

    private static function isAssoc(array $array) {
        $i = 0;
        foreach ($array as $k => $v)
            if ($k !== $i++)
                return true;
        return false;
    }

    function introspectRef(&$value) {
        if (is_array($value)) {
            $result        = new ValueImpl;
            $result->type  = Type::ARRAY1;
            $result->array = $this->arrayRefId($value);
            return $result;
        } else {
            return $this->introspect($value);
        }
    }

    private function introspectProperty(\ReflectionProperty $property, $object = null) {
        $property->setAccessible(true);
        $result            = new Property;
        $result->className = $this->removeNamespacePrefix($property->class);
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

    private function arrayRefId(array &$array) {
        foreach ($this->arrayIdRefs as $id => &$array2) {
            if (self::refEqual($array2, $array))
                return $id;
        }

        $id = $this->nextArrayId++;

        $this->arrayIdRefs[$id]  =& $array;
        $this->root->arrays[$id] = $this->introspectArray($array);
        unset($this->arrayIdRefs[$id]);

        return $id;
    }

    private static function refEqual(&$x, &$y) {
        $xOld   = $x;
        $x      = new \stdClass;
        $result = $x === $y;
        $x      = $xOld;

        return $result;
    }

    function introspectException(\Exception $e) {
        $result            = new ValueImpl;
        $result->type      = Type::EXCEPTION;
        $result->exception = $this->introspectException2($e);
        return $result;
    }

    private function introspectException2(\Exception $e = null, $includeGlobals = true) {
        if (!$e)
            return null;

        $locals = $e instanceof ErrorException ? $e->getContext() : null;

        $result            = new ExceptionImpl;
        $result->className = $this->removeNamespacePrefix(get_class($e));
        $result->code      = $e->getCode();
        $result->message   = $e->getMessage();
        $result->location  = $this->introspectLocation($e->getFile(), $e->getLine());
        $result->globals   = $includeGlobals ? $this->introspectGlobals() : null;
        $result->locals    = $this->introspectVariables($locals, $result->localsMissing,
                                                        $this->limits->maxLocalVariables);
        $result->stack     = $this->introspectStack($e->getTrace(), $result->stackMissing);
        $result->previous  = $this->introspectException2($e->getPrevious(), false);
        return $result;
    }

    private function introspectLocation($file, $line) {
        if (!$file)
            return null;
        $result         = new Location;
        $result->file   = $this->removeFileNamePrefix($file);
        $result->line   = $line;
        $result->source = $this->introspectSourceCode($file, $line);
        return $result;
    }

    private function removeFileNamePrefix($file) {
        $prefix = $this->limits->fileNamePrefix;

        if (substr($file, 0, strlen($prefix)) === $prefix)
            return (string)substr($file, strlen($prefix));
        else
            return $file;
    }

    private function introspectGlobals() {
        $result                   = new Globals;
        $result->globalVariables  = $this->introspectVariables($GLOBALS, $result->globalVariablesMissing,
                                                               $this->limits->maxGlobalVariables);
        $result->staticProperties = $this->introspectStaticProperties($result->staticPropertiesMissing);
        $result->staticVariables  = $this->introspectStaticVariables($result->staticVariablesMissing);
        return $result;
    }

    private function introspectVariables(array &$variables = null, &$missing, $max) {
        if (!is_array($variables))
            return null;
        /** @var Variable[] $results */
        $results = array();

        foreach ($variables as $name => &$value) {
            if (count($results) >= $max) {
                $missing++;
            } else {
                $result        = new Variable;
                $result->name  = $name;
                $result->value = $this->introspectRef($value);
                $results[]     = $result;
            }
        }

        return $results;
    }

    private function introspectStack(array $frames, &$missing) {
        $results = array();
        foreach ($frames as $frame) {
            if (count($results) >= $this->limits->maxStackFrames) {
                $missing++;
            } else {
                $function =& $frame['function'];
                $line     =& $frame['line'];
                $file     =& $frame['file'];
                $class    =& $frame['class'];
                $object   =& $frame['object'];
                $type     =& $frame['type'];
                $args     =& $frame['args'];

                $result               = new Stack;
                $result->functionName = $class === null ? $this->removeNamespacePrefix($function) : $function;
                $result->location     = $this->introspectLocation($file, $line);
                $result->className    = $class === null ? null : $this->removeNamespacePrefix($class);
                $result->object       = $this->objectId($object);

                if ($type === '::')
                    $result->isStatic = true;
                else if ($type === '->')
                    $result->isStatic = false;
                else
                    $result->isStatic = null;

                if ($args !== null) {
                    if ($class === null && function_exists($function))
                        $reflection = new \ReflectionFunction($function);
                    else if ($class !== null && method_exists($class, $function))
                        $reflection = new \ReflectionMethod($class, $function);
                    else
                        $reflection = null;

                    $params = $reflection ? $reflection->getParameters() : null;

                    $result->args = array();
                    foreach ($args as $i => &$arg) {
                        $param = $params && isset($params[$i]) ? $params[$i] : null;

                        $arg1              = new FunctionArg;
                        $arg1->name        = $param ? $param->getName() : null;
                        $arg1->value       = $this->introspectRef($arg);
                        $arg1->isReference = $param ? $param->isPassedByReference() : null;

                        if (!$param)
                            $arg1->typeHint = null;
                        else if ($param->isArray())
                            $arg1->typeHint = 'array';
                        else if ($param->isCallable())
                            $arg1->typeHint = 'callable';
                        else
                            $arg1->typeHint = $param->getClass() ? $this->removeNamespacePrefix($param->getClass()->name) : null;

                        $result->args[] = $arg1;
                    }
                }

                $results[] = $result;
            }
        }

        return $results;
    }

    private function introspectSourceCode($file, $line) {
        if (!$this->limits->includeSourceCode)
            return null;

        if (!@is_readable($file))
            return null;

        $contents = @file_get_contents($file);

        if (!is_string($contents))
            return null;

        $lines   = explode("\n", $contents);
        $results = array();
        $context = $this->limits->maxSourceCodeContext;
        foreach (range($line - $context, $line + $context) as $line1) {
            if (isset($lines[$line1 - 1]))
                $results[$line1] = $lines[$line1 - 1];
        }

        return $results;
    }

    private function introspectStaticProperties(&$missing) {
        $results = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                if ($property->class === $reflection->name) {
                    if (count($results) >= $this->limits->maxStaticProperties)
                        $missing++;
                    else
                        $results[] = $this->introspectProperty($property);
                }
            }
        }

        return $results;
    }

    private function introspectStaticVariables(&$missing) {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                if ($method->class !== $reflection->name)
                    continue;

                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $name => &$value) {
                    if (count($globals) >= $this->limits->maxStaticVariables) {
                        $missing++;
                    } else {
                        $variable               = new StaticVariable;
                        $variable->name         = $name;
                        $variable->value        = $this->introspectRef($value);
                        $variable->className    = $this->removeNamespacePrefix($method->class);
                        $variable->functionName = $method->getName();
                        $globals[]              = $variable;
                    }
                }
            }
        }

        foreach (get_defined_functions() as $section) {
            foreach ($section as $function) {
                $reflection      = new \ReflectionFunction($function);
                $staticVariables = $reflection->getStaticVariables();

                foreach ($staticVariables as $name => &$value2) {
                    if (count($globals) >= $this->limits->maxStaticVariables) {
                        $missing++;
                    } else {
                        $variable               = new StaticVariable;
                        $variable->name         = $name;
                        $variable->value        = $this->introspectRef($value2);
                        $variable->functionName = $this->removeNamespacePrefix($function);
                        $globals[]              = $variable;
                    }
                }
            }
        }

        return $globals;
    }

    function root(ValueImpl $value) {
        $root       = clone $this->root;
        $root->root = $value;
        return $root;
    }
}

