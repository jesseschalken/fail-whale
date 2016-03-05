<?php

namespace FailWhale;

use FailWhale\Data;
use FailWhale\Test\DummyClass1;
use FailWhale\Test\DummyClass2;

class Introspection {
    /** @var Data\Root */
    private $root;
    private $stringIds   = array();
    private $objectIds   = array();
    private $arrayIdRefs = array();
    private $limits;

    public function __construct(IntrospectionSettings $limits = null) {
        $this->root   = new Data\Root;
        $this->limits = $limits ?: new IntrospectionSettings;
    }

    public function mockException($includeGlobals = true) {
        $mock               = new Data\ExceptionData;
        $mock->className    = 'MuhMockException';
        $mock->code         = 'Dummy exception code';
        $mock->message      = <<<'s'
This is a dummy exception message.

lololool
s;
        $mock->stackMissing = 8;

        $unknown1       = new Data\Value_;
        $unknown1->type = 'hurr durr';
        $unknown2       = new Data\Value_;
        $unknown2->type = Data\Type::UNKNOWN;

        $location         = new Data\Location;
        $location->file   = '/path/to/muh/file';
        $location->line   = 9000;
        $location->source = array(
            $this->codeLine(8999, 'line'),
            $this->codeLine(9000, 'line'),
            $this->codeLine(9001, 'line'),
        );

        $globals                          = new Data\Globals;
        $globals->staticPropertiesMissing = 1;
        $globals->globalVariablesMissing  = 19;
        $globals->staticVariablesMissing  = 7;

        $prop1            = new Data\Property;
        $prop1->value     = $this->introspect(null);
        $prop1->name      = 'blahProperty';
        $prop1->access    = 'private';
        $prop1->className = 'BlahClass';
        $prop1->isDefault = false;

        $globals->staticProperties = array($prop1);

        $static1               = new Data\StaticVariable;
        $static1->name         = 'variable name';
        $static1->value        = $unknown1;
        $static1->functionName = 'blahFunction';
        $static1->className    = null;

        $static2               = new Data\StaticVariable;
        $static2->name         = 'lolStatic';
        $static2->value        = $this->introspect(null);
        $static2->functionName = 'blahMethod';
        $static2->className    = 'BlahAnotherClass';

        $globals->staticVariables = array($static1, $static2);

        $global1        = new Data\Variable;
        $global1->name  = '_SESSION';
        $global1->value = $unknown2;

        $global2        = new Data\Variable;
        $global2->name  = 'globalVariable';
        $global2->value = $this->introspect(-2734);

        $globals->globalVariables = array($global1, $global2);

        $local1        = new Data\Variable;
        $local1->name  = 'lol';
        $local1->value = $this->introspect(8);

        $local2        = new Data\Variable;
        $local2->name  = 'foo';
        $local2->value = $this->introspect('bar');

        $arg1              = new Data\FunctionArg;
        $arg1->name        = 'arg1';
        $arg1->value       = $this->introspect(new DummyClass1);
        $arg1->typeHint    = get_class(new DummyClass1);
        $arg1->isReference = false;

        $arg2              = new Data\FunctionArg;
        $arg2->name        = 'anArray';
        $arg2->value       = $this->introspect(new DummyClass2);
        $arg2->isReference = true;
        $arg2->typeHint    = 'array';

        $stack1                = new Data\Stack;
        $stack1->args          = array($arg1, $arg2);
        $stack1->functionName  = 'aFunction';
        $stack1->className     = 'DummyClass1';
        $stack1->isStatic      = false;
        $stack1->location      = clone $location;
        $stack1->object        = $this->objectId(new DummyClass1);
        $stack1->argsMissing   = 3;
        $stack1->localsMissing = 5;
        $stack1->locals        = array($local1, $local2);

        $stack2                   = new Data\Stack;
        $stack2->args             = array();
        $stack2->functionName     = 'aFunction';
        $stack2->className        = null;
        $stack2->isStatic         = null;
        $stack2->location         = clone $location;
        $stack2->location->source = null;
        $stack2->object           = null;
        $stack2->argsMissing      = 0;

        $stack3               = new Data\Stack;
        $stack3->args         = null;
        $stack3->functionName = 'aFunction';
        $stack3->className    = 'AnClass';
        $stack3->isStatic     = null;
        $stack3->location     = null;
        $stack3->object       = null;
        $stack3->argsMissing  = 0;

        $mock->stack = array($stack1, $stack2, $stack3);

        $exception             = new Data\Exception_;
        $exception->globals    = $includeGlobals ? $globals : null;
        $exception->exceptions = array($mock);

        $value            = new Data\Value_;
        $value->type      = Data\Type::EXCEPTION;
        $value->exception = $exception;
        return $value;
    }

    public function introspect($value) {
        $result = new Data\Value_;

        if (is_string($value)) {
            $result->type   = Data\Type::STRING;
            $result->string = $this->stringId($value);
        } else if (is_int($value)) {
            $result->type = Data\Type::INT;
            $result->int  = $value;
        } else if (is_bool($value)) {
            $result->type = $value ? Data\Type::TRUE : Data\Type::FALSE;
        } else if (is_null($value)) {
            $result->type = Data\Type::NULL;
        } else if (is_float($value)) {
            if ($value === INF) {
                $result->type = Data\Type::POS_INF;
            } else if ($value === -INF) {
                $result->type = Data\Type::NEG_INF;
            } else if (is_nan($value)) {
                $result->type = Data\Type::NAN;
            } else {
                $result->type  = Data\Type::FLOAT;
                $result->float = $value;
            }
        } else if (is_array($value)) {
            $result->type  = Data\Type::ARRAY1;
            $result->array = $this->arrayId($value);
        } else if (is_object($value)) {
            $result->type   = Data\Type::OBJECT;
            $result->object = $this->objectId($value);
        } else if (is_resource($value)) {
            $result->type           = Data\Type::RESOURCE;
            $result->resource       = new Data\Resource_;
            $result->resource->id   = (int)$value;
            $result->resource->type = get_resource_type($value);
        } else {
            $result->type = Data\Type::UNKNOWN;
        }

        return $result;
    }

    private function objectId($object) {
        if (!$object) {
            return null;
        }
        $id =& $this->objectIds[spl_object_hash($object)];
        if ($id === null) {
            $id = count($this->root->objects);

            $this->root->objects[$id] = null;
            $this->root->objects[$id] = $this->introspectObject($object);
            return $id;
        }
        return $id;
    }

    private function stringId($value) {
        $id =& $this->stringIds[$value];
        if ($id === null) {
            $id = count($this->root->strings);

            $this->root->strings[$id] = null;

            $maxLength = $this->limits->maxStringLength;
            $maxLength = $maxLength === INF ? PHP_INT_MAX : $maxLength;

            $string               = new Data\String_;
            $string->bytes        = (string)substr($value, 0, $maxLength);
            $string->bytesMissing = strlen($value) - strlen($string->bytes);

            $this->root->strings[$id] = $string;

            return $id;
        }
        return $id;
    }

    private function arrayId(array $value) {
        $id = count($this->root->arrays);

        $this->root->arrays[$id] = null;
        $this->root->arrays[$id] = $this->introspectArray($value);
        return $id;
    }

    private function introspectObject($object) {
        $result             = new Data\Object_;
        $result->className  = $this->removeNamespacePrefix(get_class($object));
        $result->hash       = spl_object_hash($object);
        $result->properties = $this->introspectObjectProperties($object, $result->propertiesMissing);
        return $result;
    }

    private function removeNamespacePrefix($name) {
        if ($name === null) {
            return null;
        }

        $name   = "\\$name";
        $prefix = $this->limits->namespacePrefix;

        if (substr($name, 0, strlen($prefix)) === $prefix) {
            return (string)substr($name, strlen($prefix));
        } else {
            return $name;
        }
    }

    private function introspectArray(array $array) {
        $result                = new Data\Array_;
        $result->isAssociative = \FailWhale\_Internal\array_is_assoc($array);

        foreach ($array as $key => &$value) {
            if (count($result->entries) >= $this->limits->maxArrayEntries) {
                $result->entriesMissing++;
            } else {
                $entry             = new Data\ArrayEntry;
                $entry->key        = $this->introspect($key);
                $entry->value      = $this->introspectRef($value);
                $result->entries[] = $entry;
            }
        }

        return $result;
    }

    private function introspectObjectProperties($object, &$missing) {
        /** @var Data\Property[] $results */
        $results = array();

        for ($reflection = new \ReflectionObject($object);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic() && $property->class === $reflection->name) {
                    if (count($results) >= $this->limits->maxObjectProperties) {
                        $missing++;
                    } else {
                        $results[] = $this->introspectProperty($property, $object);
                    }
                }
            }
        }

        return $results;
    }

    public function introspectRef(&$value) {
        if (is_array($value)) {
            $result        = new Data\Value_;
            $result->type  = Data\Type::ARRAY1;
            $result->array = $this->arrayRefId($value);
            return $result;
        } else {
            return $this->introspect($value);
        }
    }

    private function introspectProperty(\ReflectionProperty $property, $object = null) {
        $property->setAccessible(true);
        $result            = new Data\Property;
        $result->className = $this->removeNamespacePrefix($property->class);
        $result->name      = $property->name;
        $result->value     = $this->introspect($property->getValue($object));
        $result->isDefault = $property->isDefault();

        if ($property->isPrivate()) {
            $result->access = 'private';
        } else if ($property->isProtected()) {
            $result->access = 'protected';
        } else if ($property->isPublic()) {
            $result->access = 'public';
        }

        return $result;
    }

    private function arrayRefId(array &$array) {
        foreach ($this->arrayIdRefs as $id => &$array2) {
            if (\FailWhale\_Internal\ref_eq($array2, $array)) {
                return $id;
            }
        }

        $id = count($this->root->arrays);

        $this->arrayIdRefs[$id]  =& $array;
        $this->root->arrays[$id] = null;
        $this->root->arrays[$id] = $this->introspectArray($array);
        unset($this->arrayIdRefs[$id]);

        return $id;
    }

    public function introspectException(\Exception $e) {
        $result                     = new Data\Value_;
        $result->type               = Data\Type::EXCEPTION;
        $result->exception          = new Data\Exception_;
        $result->exception->globals = $this->introspectGlobals();

        for (; $e instanceof \Exception; $e = $e->getPrevious()) {
            $result->exception->exceptions[] = $this->introspectException2($e);
        }

        return $result;
    }

    private function introspectException2(\Exception $e) {
        $class   = get_class($e);
        $code    = $e->getCode();
        $message = $e->getMessage();
        $trace   = $e->getTrace();
        $file    = $e->getFile();
        $line    = $e->getLine();
        $locals  = $e instanceof ErrorExceptionWithContext ? $e->getContext() : null;

        $result            = new Data\ExceptionData;
        $result->className = $this->removeNamespacePrefix($class);
        $result->code      = $code;
        $result->message   = $message;
        $result->stack     = $this->introspectStack($trace, $result->stackMissing, $file, $line, $locals);
        return $result;
    }

    private function introspectLocation($file, $line) {
        if (!$file) {
            return null;
        }
        $result         = new Data\Location;
        $result->file   = $this->removeFileNamePrefix($file);
        $result->line   = $line;
        $result->source = $this->introspectSourceCode($file, $line);
        return $result;
    }

    private function removeFileNamePrefix($file) {
        $prefix = $this->limits->fileNamePrefix;

        if (substr($file, 0, strlen($prefix)) === $prefix) {
            return (string)substr($file, strlen($prefix));
        } else {
            return $file;
        }
    }

    private function introspectGlobals() {
        $result                   = new Data\Globals;
        $result->globalVariables  = $this->introspectVariables($GLOBALS, $result->globalVariablesMissing,
            $this->limits->maxGlobalVariables);
        $result->staticProperties = $this->introspectStaticProperties($result->staticPropertiesMissing);
        $result->staticVariables  = $this->introspectStaticVariables($result->staticVariablesMissing);
        return $result;
    }

    private function introspectVariables(array $variables = null, &$missing, $max) {
        if (!is_array($variables)) {
            return null;
        }
        /** @var Data\Variable[] $results */
        $results = array();

        foreach ($variables as $name => &$value) {
            if (count($results) >= $max) {
                $missing++;
            } else {
                $result        = new Data\Variable;
                $result->name  = $name;
                $result->value = $this->introspectRef($value);
                $results[]     = $result;
            }
        }

        return $results;
    }

    private function introspectStack(array $frames, &$missing, $file, $line, array $locals = null) {
        $results  = array();
        $frames[] = array(
            'function' => ltrim($this->limits->namespacePrefix, '\\') . '{main}',
            'args'     => array(),
        );

        foreach ($frames as $frame) {
            if (count($results) >= $this->limits->maxStackFrames) {
                $missing++;
            } else {
                $function = \FailWhale\_Internal\ref_get($frame['function']);
                $line2    = \FailWhale\_Internal\ref_get($frame['line']);
                $file2    = \FailWhale\_Internal\ref_get($frame['file']);
                $class    = \FailWhale\_Internal\ref_get($frame['class']);
                $object   = \FailWhale\_Internal\ref_get($frame['object']);
                $type     = \FailWhale\_Internal\ref_get($frame['type']);
                $args     = \FailWhale\_Internal\ref_get($frame['args']);

                $result               = new Data\Stack;
                $result->functionName = $class === null ? $this->removeNamespacePrefix($function) : $function;
                $result->location     = $this->introspectLocation($file, $line);
                $result->className    = $class === null ? null : $this->removeNamespacePrefix($class);
                $result->object       = $this->objectId($object);
                $result->locals       = $this->introspectVariables($locals, $result->localsMissing,
                    $this->limits->maxLocalVariables);

                if ($type === '::') {
                    $result->isStatic = true;
                } else if ($type === '->') {
                    $result->isStatic = false;
                } else {
                    $result->isStatic = null;
                }

                if ($args !== null) {
                    if ($class === null && function_exists($function)) {
                        $reflection = new \ReflectionFunction($function);
                    } else if ($class !== null && method_exists($class, $function)) {
                        $reflection = new \ReflectionMethod($class, $function);
                    } else {
                        $reflection = null;
                    }

                    $params = $reflection ? $reflection->getParameters() : null;

                    $result->args = array();
                    foreach ($args as $i => &$arg) {
                        $param = $params && isset($params[$i]) ? $params[$i] : null;

                        $arg1              = new Data\FunctionArg;
                        $arg1->name        = $param ? $param->getName() : null;
                        $arg1->value       = $this->introspectRef($arg);
                        $arg1->isReference = $param ? $param->isPassedByReference() : null;

                        if (!$param) {
                            $arg1->typeHint = null;
                        } else if ($param->isArray()) {
                            $arg1->typeHint = 'array';
                        } else if (method_exists($param, 'isCallable') && $param->isCallable()) {
                            $arg1->typeHint = 'callable';
                        } else {
                            $arg1->typeHint = $this->getParameterClass($param);
                        }

                        $result->args[] = $arg1;
                    }
                }

                $results[] = $result;

                $line   = $line2;
                $file   = $file2;
                $locals = null;
            }
        }

        return $results;
    }

    private function getParameterClass(\ReflectionParameter $param) {
        preg_match(':^Parameter #\d+ \[ \<.*?> (.*?) &?\$\w+ \]$:s', "$param", $matches);
        return isset($matches[1]) ? $this->removeNamespacePrefix($matches[1]) : null;
    }

    private function introspectSourceCode($file, $line) {
        if (!$this->limits->includeSourceCode) {
            return null;
        }

        if (!@is_readable($file)) {
            return null;
        }

        $contents = @file_get_contents($file);

        if (!is_string($contents)) {
            return null;
        }

        $lines   = explode("\n", $contents);
        $results = array();
        $context = $this->limits->maxSourceCodeContext;
        foreach (range($line - $context, $line + $context) as $line1) {
            if (isset($lines[$line1 - 1])) {
                $results[] = $this->codeLine($line1, $lines[$line1 - 1]);
            }
        }

        return $results;
    }

    private function codeLine($line, $code) {
        $codeLine        = new Data\CodeLine;
        $codeLine->line  = $line;
        $codeLine->code  = $code;
        return $codeLine;
    }

    private function introspectStaticProperties(&$missing) {
        $results = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                if ($property->class === $reflection->name) {
                    if (count($results) >= $this->limits->maxStaticProperties) {
                        $missing++;
                    } else {
                        $results[] = $this->introspectProperty($property);
                    }
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
                if ($method->class !== $reflection->name) {
                    continue;
                }

                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $name => &$value) {
                    if (count($globals) >= $this->limits->maxStaticVariables) {
                        $missing++;
                    } else {
                        $variable               = new Data\StaticVariable;
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
                        $variable               = new Data\StaticVariable;
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

    public function root(Data\Value_ $value) {
        $root       = clone $this->root;
        $root->root = $value;
        return $root;
    }
}

