<?php

namespace PrettyPrinter\Introspection {
    use PrettyPrinter\Values;

    interface ExceptionHasFullTrace {
        /**
         * @return array
         */
        function getFullTrace();
    }

    interface ExceptionHasLocalVariables {
        /**
         * @return array|null
         */
        function getLocalVariables();
    }

    class ArrayCache {
        /** @var ArrayCacheEntry[] */
        private $entries = array();

        function get(Introspection $i, Wrapped $array) {
            foreach ($this->entries as $entry)
                if ($entry->equals($array))
                    return $entry->result();

            return Values\ValueArray::introspect($i, $array, $this);
        }

        function insert(Wrapped $array, Values\ValueArray $result) {
            $this->entries[] = new ArrayCacheEntry($array, $result);
        }
    }

    class ArrayCacheEntry {
        /** @var Wrapped */
        private $array;
        /** @var Values\ValueArray */
        private $result;

        function __construct(Wrapped $ref, Values\ValueArray $result) {
            $this->array  = $ref;
            $this->result = $result;
        }

        function equals(Wrapped $array) { return $this->array->equals($array); }

        function result() { return $this->result; }
    }

    class ObjectCache {
        /** @var ObjectCacheEntry[] */
        private $entries = array();

        /**
         * @param Introspection $i
         * @param object        $object
         *
         * @return Values\ValueObject
         */
        function get(Introspection $i, $object) {
            if (isset($this->entries[spl_object_hash($object)]))
                return $this->entries[spl_object_hash($object)]->result();

            return Values\ValueObject::introspect($i, $object, $this);
        }

        function insert($object, Values\ValueObject $result) {
            $this->entries[spl_object_hash($object)] = new ObjectCacheEntry($object, $result);
        }
    }

    class ObjectCacheEntry {
        private $object, $result;

        function __construct($object, Values\ValueObject $result) {
            $this->object = $object;
            $this->result = $result;
        }

        function result() { return $this->result; }
    }

    class Introspection {
        private $objectCache;
        private $arrayCache;

        function __construct() {
            $this->objectCache = new ObjectCache;
            $this->arrayCache  = new ArrayCache;
        }

        /**
         * @param \ReflectionProperty|\ReflectionMethod $property
         *
         * @return string
         */
        function propertyOrMethodAccess($property) {
            return $property->isPrivate() ? 'private' : ($property->isPublic() ? 'public' : 'protected');
        }

        function introspectException(\Exception $e) { return Values\ValueException::introspect($this, $e); }

        function introspect(Wrapped $k) {
            return $k->introspect($this, $this->objectCache, $this->arrayCache);
        }
    }

    class Wrapped {
        private $ref;

        static function val($x = null) { return new self($x); }

        static function ref(&$x = null) { return new self($x); }

        private function __construct(&$ref) {
            $this->ref =& $ref;
        }

        function equals(self $ref) {
            $old       = $this->ref;
            $this->ref = new \stdClass;
            $result    = $this->ref === $ref->ref;
            $this->ref = $old;

            return $result;
        }

        function get() { return $this->ref; }

        function introspect(Introspection $i, ObjectCache $o, ArrayCache $a) {
            $value = $this->ref;

            if (is_string($value))
                return new Values\ValueString($value);

            if (is_int($value))
                return new Values\ValueInt($value);

            if (is_bool($value))
                return new Values\ValueBool($value);

            if (is_null($value))
                return new Values\ValueNull($value);

            if (is_float($value))
                return new Values\ValueFloat($value);

            if (is_array($value))
                return $a->get($i, $this);

            if (is_object($value))
                return $o->get($i, $value);

            if (is_resource($value))
                return Values\ValueResource::introspect($value);

            return new Values\ValueUnknown;
        }
    }
}

namespace PrettyPrinter\Values {
    use ErrorHandler\Exception;
    use PrettyPrinter\Introspection\ArrayCache;
    use PrettyPrinter\Introspection\ExceptionHasFullTrace;
    use PrettyPrinter\Introspection\ExceptionHasLocalVariables;
    use PrettyPrinter\Introspection\Introspection;
    use PrettyPrinter\Introspection\ObjectCache;
    use PrettyPrinter\Introspection\Wrapped;
    use PrettyPrinter\PrettyPrinter;
    use PrettyPrinter\Test\DummyClass1;
    use PrettyPrinter\Test\DummyClass2;
    use PrettyPrinter\Utils\Text;

    abstract class Value {
        private static $nextId = 0;
        private $id;

        protected function __construct() {
            $this->id = self::$nextId++;
        }

        /**
         * @param PrettyPrinter $settings
         *
         * @return Text
         */
        final function render(PrettyPrinter $settings) { return $settings->render($this); }

        abstract function serialize(Serialization $s);

        function serialuzeUnserialize() {
            return Deserialization::deserializeWhole(Serialization::serializeWhole($this));
        }

        function id() { return $this->id; }

        /**
         * @param PrettyPrinter $settings
         *
         * @return Text
         */
        abstract function renderImpl(PrettyPrinter $settings);

        /**
         * @return self[]
         */
        function subValues() { return array(); }
    }

    class ValueArray extends Value {
        /**
         * @param Introspection $introspection
         * @param Wrapped       $ref
         * @param ArrayCache    $cache
         *
         * @return ValueArray
         */
        static function introspect(Introspection $introspection, Wrapped $ref, ArrayCache $cache) {
            $self = new self;
            $cache->insert($ref, $self);

            $array               = $ref->get();
            $self->isAssociative = self::isArrayAssociative($array);

            foreach ($array as $k => &$v)
                $self->entries[] = ArrayEntry::introspect($introspection, Wrapped::val($k), Wrapped::ref($v));

            return $self;
        }

        private static function isArrayAssociative(array $array) {
            $i = 0;

            /** @noinspection PhpUnusedLocalVariableInspection */
            foreach ($array as $k => &$v)
                if ($k !== $i++)
                    return true;

            return false;
        }

        private $isAssociative = false;
        /** @var ArrayEntry[] */
        private $entries = array();

        function isAssociative() { return $this->isAssociative; }

        function subValues() {
            $x = parent::subValues();

            foreach ($this->entries as $kvPair) {
                $x[] = $kvPair->key();
                $x[] = $kvPair->value();
            }

            return $x;
        }

        function entries() { return $this->entries; }

        function renderImpl(PrettyPrinter $settings) { return $settings->renderArray($this); }

        function serialize(Serialization $s) {
            $s->addArray($this);

            return array('type' => 'array', 'array' => $this->id());
        }

        function serializeArray(Serialization $s) {
            $entries = array();

            foreach ($this->entries as $entry)
                $entries[] = $entry->serialize($s);

            return array(
                'isAssociative' => $this->isAssociative,
                'entries'       => $entries,
            );
        }

        /**
         * @param Deserialization $pool
         * @param                 $id
         * @param                 $v
         * @param ValueArray[]    $cache
         *
         * @return ValueArray
         */
        static function deserialize(Deserialization $pool, $id, $v, array &$cache) {
            if (isset($cache[$id]))
                return $cache[$id];

            $self                = new self;
            $self->isAssociative = $v['isAssociative'];
            $cache[$id]          = $self;

            foreach ($v['entries'] as $entry)
                $self->entries[] = ArrayEntry::deserialize($pool, $entry);

            return $self;
        }
    }

    class ArrayEntry {
        static function introspect(Introspection $introspection, Wrapped $k, Wrapped $v) {
            $self        = new self;
            $self->key   = $introspection->introspect($k);
            $self->value = $introspection->introspect($v);

            return $self;
        }

        static function deserialize(Deserialization $pool, $value) {
            $self        = new self;
            $self->key   = $pool->deserialize($value['key']);
            $self->value = $pool->deserialize($value['value']);

            return $self;
        }

        /** @var Value */
        private $key;
        /** @var Value */
        private $value;

        private function __construct() { }

        function key() { return $this->key; }

        function value() { return $this->value; }

        function serialize(Serialization $s) {
            return array(
                'key'   => $s->serialize($this->key),
                'value' => $s->serialize($this->value),
            );
        }
    }

    class ValueBool extends Value {
        private $bool;

        /**
         * @param bool $bool
         */
        function __construct($bool) {
            $this->bool = $bool;
        }

        function renderImpl(PrettyPrinter $settings) { return $settings->text($this->bool ? 'true' : 'false'); }

        function serialize(Serialization $s) { return array('type' => 'bool', 'bool' => $this->bool); }
    }

    class ValueException extends Value {
        static function introspect(Introspection $i, \Exception $e) {
            $self          = self::introspectException($i, $e);
            $self->globals = Variable::introspectGlobals($i);

            return $self;
        }

        function subValues() {
            $x = parent::subValues();

            if ($this->locals !== null)
                foreach ($this->locals as $local)
                    $x[] = $local->value();

            if ($this->globals !== null)
                foreach ($this->globals as $global)
                    $x[] = $global->value();

            foreach ($this->stack as $frame)
                foreach ($frame->subValues() as $c)
                    $x[] = $c;

            if ($this->previous !== null)
                foreach ($this->previous->subValues() as $c)
                    $x[] = $c;

            return $x;
        }

        private static function introspectException(Introspection $i, \Exception $e) {
            $self            = new self;
            $self->className = get_class($e);
            $self->code      = $e->getCode();
            $self->message   = $e->getMessage();
            $self->line      = $e->getLine();
            $self->file      = $e->getFile();

            if ($e->getPrevious() !== null)
                $self->previous = self::introspectException($i, $e->getPrevious());

            if ($e instanceof ExceptionHasLocalVariables && $e->getLocalVariables() !== null) {
                $self->locals = array();

                $locals = $e->getLocalVariables();
                foreach ($locals as $name => &$value)
                    $self->locals[] = Variable::introspect($i, $name, Wrapped::ref($value));
            }

            foreach ($e instanceof ExceptionHasFullTrace ? $e->getFullTrace() : $e->getTrace() as $frame)
                $self->stack[] = FunctionCall::introspect($i, $frame);

            return $self;
        }

        static function mock(Introspection $param) {
            $self            = new self;
            $self->className = 'MuhMockException';
            $self->message   = <<<'s'
This is a dummy exception message.

lololool
s;
            $self->code      = 'Dummy exception code';
            $self->file      = '/the/path/to/muh/file';
            $self->line      = 9000;
            $self->locals    = array(Variable::introspect($param, 'lol', Wrapped::val(8)),
                                     Variable::introspect($param, 'foo', Wrapped::val('bar')));

            $self->stack   = FunctionCall::mock($param);
            $self->globals = Variable::mockGlobals($param);

            return $self;
        }

        private $className;
        /** @var FunctionCall[] */
        private $stack = array();
        /** @var Variable[]|null */
        private $locals;
        private $code;
        private $message;
        /** @var self|null */
        private $previous;
        private $file;
        private $line;
        /** @var Variable[]|null */
        private $globals;

        function className() { return $this->className; }

        function code() { return $this->code; }

        function file() { return $this->file; }

        function globals() { return $this->globals; }

        function line() { return $this->line; }

        function locals() { return $this->locals; }

        function message() { return $this->message; }

        function previous() { return $this->previous; }

        function renderImpl(PrettyPrinter $settings) { return $settings->renderExceptionWithGlobals($this); }

        function stack() { return $this->stack; }

        function serialize(Serialization $s) {
            $stack    = array();
            $locals   = null;
            $previous = $this->previous === null ? null : $s->serialize($this->previous);
            $globals  = null;

            foreach ($this->stack as $frame)
                $stack[] = $frame->serialize($s);

            if ($this->locals !== null) {
                $locals = array();

                foreach ($this->locals as $local)
                    $locals[] = $local->serialize($s);
            }

            if ($this->globals !== null) {
                $globals = array();

                foreach ($this->globals as $global)
                    $globals[] = $global->serialize($s);
            }

            return array(
                'type'      => 'exception',
                'exception' => array(
                    'className' => $this->className,
                    'stack'     => $stack,
                    'locals'    => $locals,
                    'code'      => $this->code,
                    'message'   => $this->message,
                    'previous'  => $previous,
                    'file'      => $this->file,
                    'line'      => $this->line,
                    'globals'   => $globals,
                )
            );
        }

        static function deserialize(Deserialization $pool, $v) {
            $self            = new self;
            $self->className = $v['className'];
            $self->code      = $v['code'];
            $self->message   = $v['message'];
            $self->file      = $v['file'];
            $self->line      = $v['line'];

            foreach ($v['stack'] as $frame)
                $self->stack[] = FunctionCall::deserialize($pool, $frame);

            $self->previous = $v['previous'] === null ? null : self::deserialize($pool, $v['previous']);

            if ($v['locals'] !== null) {
                $self->locals = array();

                foreach ($v['locals'] as $local)
                    $self->locals[] = Variable::deserialize($pool, $local);
            }

            if ($v['globals'] !== null) {
                $self->globals = array();

                foreach ($v['globals'] as $global)
                    $self->globals[] = Variable::deserialize($pool, $global);
            }

            return $self;
        }
    }

    class Variable {
        static function deserialize(Deserialization $pool, $prop) {
            $self               = new self($prop['name'], $pool->deserialize($prop['value']));
            $self->functionName = $prop['functionName'];
            $self->access       = $prop['access'];
            $self->isGlobal     = $prop['isGlobal'];
            $self->isStatic     = $prop['isStatic'];
            $self->className    = $prop['className'];

            return $self;
        }

        /**
         * @param Introspection $i
         *
         * @return self[]
         */
        static function introspectGlobals(Introspection $i) {
            $globals = array();

            foreach ($GLOBALS as $variableName => &$globalValue) {
                if ($variableName !== 'GLOBALS') {
                    $self           = self::introspect($i, $variableName, Wrapped::ref($globalValue));
                    $self->isGlobal = true;

                    $globals [] = $self;
                }
            }

            foreach (get_declared_classes() as $class) {
                $reflection = new \ReflectionClass($class);

                foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                    $property->setAccessible(true);

                    $self            = new self($property->name, $i->introspect(Wrapped::val($property->getValue())));
                    $self->className = $property->class;
                    $self->access    = $i->propertyOrMethodAccess($property);
                    $self->isStatic  = true;

                    $globals[] = $self;
                }

                foreach ($reflection->getMethods() as $method) {
                    $staticVariables = $method->getStaticVariables();

                    foreach ($staticVariables as $variableName => &$varValue) {
                        $self               = self::introspect($i, $variableName, Wrapped::ref($varValue));
                        $self->className    = $method->class;
                        $self->access       = $i->propertyOrMethodAccess($method);
                        $self->functionName = $method->getName();
                        $self->isStatic     = $method->isStatic();

                        $globals[] = $self;
                    }
                }
            }

            foreach (get_defined_functions() as $section) {
                foreach ($section as $function) {
                    $reflection      = new \ReflectionFunction($function);
                    $staticVariables = $reflection->getStaticVariables();

                    foreach ($staticVariables as $propertyName => &$varValue) {
                        $self               = self::introspect($i, $propertyName, Wrapped::ref($varValue));
                        $self->functionName = $function;

                        $globals[] = $self;
                    }
                }
            }

            return $globals;
        }

        static function introspect(Introspection $i, $name, Wrapped $value) {
            return new self($name, $i->introspect($value));
        }

        static function introspectObjectProperties(Introspection $i, $object) {
            $properties = array();

            for ($reflection = new \ReflectionObject($object);
                 $reflection !== false;
                 $reflection = $reflection->getParentClass()) {
                foreach ($reflection->getProperties() as $property) {
                    if ($property->isStatic() || $property->class !== $reflection->name)
                        continue;

                    $property->setAccessible(true);

                    $self            = self::introspect($i, $property->name, Wrapped::val($property->getValue($object)));
                    $self->className = $property->class;
                    $self->access    = $i->propertyOrMethodAccess($property);
                    $self->isStatic  = false;

                    $properties[] = $self;
                }
            }

            return $properties;
        }

        static function mockGlobals(Introspection $param) {
            //  private static BlahClass::$blahProperty                       = null;
            //  function BlahAnotherClass()::static $public                   = null;
            //  global ${"lol global"}                                        = null;
            //  function BlahYetAnotherClass::blahMethod()::static $lolStatic = null;
            //  global $blahVariable                                          = null;

            $null = $param->introspect(Wrapped::val(null));

            $globals = array();

            $self            = new self('blahProperty', $null);
            $self->className = 'BlahClass';
            $self->access    = 'private';
            $self->isStatic  = true;

            $globals[] = $self;

            $self               = new self('public', $null);
            $self->functionName = 'BlahAnotherClass';

            $globals[] = $self;

            $self           = new self('lol global', $null);
            $self->isGlobal = true;

            $globals[] = $self;

            $self               = new self('lolStatic', $null);
            $self->functionName = 'blahMethod';
            $self->className    = 'BlahYetAnotherClass';
            $self->isStatic     = true;

            $globals[] = $self;

            $self           = new self('blahVariable', $null);
            $self->isGlobal = true;

            $globals[] = $self;

            return $globals;
        }

        private $name;
        private $value;
        private $className;
        private $functionName;
        private $access;
        private $isGlobal = false;
        private $isStatic = false;

        /**
         * @param string $name
         * @param Value  $value
         */
        private function __construct($name, Value $value) {
            $this->value = $value;
            $this->name  = $name;
        }

        function render(PrettyPrinter $settings) {
            if ($this->className !== null) {
                if ($this->functionName !== null) {
                    $prefix = $settings->text("function $this->className::$this->functionName()::static ");
                } else if ($this->isStatic) {
                    $prefix = $settings->text("$this->access static $this->className::");
                } else {
                    $prefix = $settings->text("$this->access ");
                }
            } else if ($this->functionName !== null) {
                $prefix = $settings->text("function $this->functionName()::static ");
            } else if ($this->isGlobal) {
                $prefix = $settings->text(in_array($this->name, array('_POST', '_GET', '_SESSION',
                                                                      '_COOKIE', '_FILES',
                                                                      '_REQUEST', '_ENV',
                                                                      '_SERVER'), true) ? '' : 'global ');
            } else {
                $prefix = $settings->text();
            }

            return $prefix->appendLines($settings->renderVariable($this->name));
        }

        function serialize(Serialization $s) {
            return array(
                'name'         => $this->name,
                'value'        => $s->serialize($this->value),
                'className'    => $this->className,
                'functionName' => $this->functionName,
                'access'       => $this->access,
                'isGlobal'     => $this->isGlobal,
                'isStatic'     => $this->isStatic,
            );
        }

        function value() { return $this->value; }
    }

    class FunctionCall {
        static function deserialize(Deserialization $pool, $frame) {
            $self            = new self($frame['functionName']);
            $self->isStatic  = $frame['isStatic'];
            $self->file      = $frame['file'];
            $self->line      = $frame['line'];
            $self->className = $frame['className'];
            $self->object    = $frame['object'] === null ? null : $pool->deserialize($frame['object']);

            if ($frame['args'] !== null) {
                $self->args = array();

                foreach ($frame['args'] as $arg)
                    $self->args [] = $pool->deserialize($arg);
            }

            return $self;
        }

        static function introspect(Introspection $i, array $frame) {
            $self = new self($frame['function']);

            if (array_key_exists('file', $frame))
                $self->file = $frame['file'];

            if (array_key_exists('line', $frame))
                $self->line = $frame['line'];

            if (array_key_exists('class', $frame))
                $self->className = $frame['class'];

            if (array_key_exists('args', $frame)) {
                $self->args = array();

                foreach ($frame['args'] as &$arg)
                    $self->args[] = $i->introspect(Wrapped::ref($arg));
            }

            if (array_key_exists('object', $frame))
                $self->object = $i->introspect(Wrapped::val($frame['object']));

            if (array_key_exists('type', $frame))
                $self->isStatic = $frame['type'] === '::';

            return $self;
        }

        /**
         * @param Introspection $param
         *
         * @return self[]
         */
        static function mock(Introspection $param) {
            $stack = array();

            $self            = new self('aFunction');
            $self->args      = array($param->introspect(Wrapped::val(new DummyClass2)));
            $self->file      = '/path/to/muh/file';
            $self->line      = 1928;
            $self->object    = $param->introspect(Wrapped::val(new DummyClass1));
            $self->className = 'DummyClass1';

            $stack[] = $self;

            $self       = new self('aFunction');
            $self->args = array($param->introspect(Wrapped::val(new DummyClass2)));
            $self->file = '/path/to/muh/file';
            $self->line = 1928;

            $stack[] = $self;

            return $stack;
        }

        private $className;
        private $functionName;
        /** @var Value[]|null */
        private $args;
        /** @var Value|null */
        private $object;
        private $isStatic;
        private $file;
        private $line;

        /**
         * @param string $functionName
         */
        private function __construct($functionName) {
            $this->functionName = $functionName;
        }

        function subValues() {
            $x = array();

            if ($this->args !== null)
                foreach ($this->args as $arg)
                    $x[] = $arg;

            if ($this->object !== null)
                $x[] = $this->object;

            return $x;
        }

        function location() {
            return $this->file === null ? '[internal function]' : "$this->file:$this->line";
        }

        function renderArgs(PrettyPrinter $settings) {
            if ($this->args === null)
                return $settings->text("( ? )");

            if ($this->args === array())
                return $settings->text("()");

            $pretties    = array();
            $isMultiLine = false;

            foreach ($this->args as $arg) {
                $pretty      = $arg->render($settings);
                $isMultiLine = $isMultiLine || $pretty->count() > 1;
                $pretties[]  = $pretty;
            }

            $result = $settings->text();

            foreach ($pretties as $k => $pretty) {
                if ($k !== 0)
                    $result->append(', ');

                if ($isMultiLine)
                    $result->addLines($pretty);
                else
                    $result->appendLines($pretty);
            }

            return $result->wrap("( ", " )");
        }

        function render(PrettyPrinter $settings) {
            return $this->prefix($settings)
                        ->append($this->functionName)
                        ->appendLines($this->renderArgs($settings));
        }

        function prefix(PrettyPrinter $settings) {
            if ($this->object !== null)
                return $this->object->render($settings)->append('->');

            if ($this->className !== null)
                return $settings->text($this->isStatic ? "$this->className::" : "$this->className->");

            return $settings->text();
        }

        function serialize(Serialization $s) {
            $args   = null;
            $object = $this->object === null ? null : $s->serialize($this->object);

            if ($this->args !== null) {
                $args = array();

                foreach ($this->args as $arg)
                    $args[] = $s->serialize($arg);
            }

            return array(
                'className'    => $this->className,
                'functionName' => $this->functionName,
                'args'         => $args,
                'object'       => $object,
                'isStatic'     => $this->isStatic,
                'file'         => $this->file,
                'line'         => $this->line,
            );
        }
    }

    class ValueFloat extends Value {
        private $float;

        /**
         * @param float $float
         */
        function __construct($float) {
            $this->float = $float;
        }

        function renderImpl(PrettyPrinter $settings) { return $settings->text($this->toPHP()); }

        function serialize(Serialization $s) { return array('type' => 'float', 'float' => $this->toPHP()); }

        private function toPHP() {
            $int = (int)$this->float;

            return "$int" === "$this->float" ? "$this->float.0" : "$this->float";
        }
    }

    class ValueInt extends Value {
        private $int;

        /**
         * @param int $int
         */
        function __construct($int) { $this->int = $int; }

        function renderImpl(PrettyPrinter $settings) { return $settings->text("$this->int"); }

        function serialize(Serialization $s) { return array('type' => 'int', 'int' => $this->int); }
    }

    class ValueNull extends Value {
        function renderImpl(PrettyPrinter $settings) { return $settings->text('null'); }

        function serialize(Serialization $s) { return array('type' => 'null'); }

        function __construct() {
            parent::__construct();
        }
    }

    class ValueObject extends Value {
        /**
         * @param Introspection $i
         * @param object        $object
         * @param ObjectCache   $cache
         *
         * @return ValueObject
         */
        static function introspect(Introspection $i, $object, ObjectCache $cache) {
            $self = new self;
            $cache->insert($object, $self);

            $self->hash       = spl_object_hash($object);
            $self->className  = get_class($object);
            $self->properties = Variable::introspectObjectProperties($i, $object);

            return $self;
        }

        function subValues() {
            $x = parent::subValues();

            foreach ($this->properties as $p)
                $x[] = $p->value();

            return $x;
        }

        private $hash;
        private $className;
        /** @var Variable[] */
        private $properties = array();

        function className() { return $this->className; }

        function properties() { return $this->properties; }

        function renderImpl(PrettyPrinter $settings) { return $settings->renderObject($this); }

        function serialize(Serialization $s) {
            $s->addObject($this);

            return array('type' => 'object', 'object' => $this->id());
        }

        function serializeObject(Serialization $s) {
            $properties = array();

            foreach ($this->properties as $prop)
                $properties[] = $prop->serialize($s);

            return array(
                'className'  => $this->className,
                'hash'       => $this->hash,
                'properties' => $properties,
            );
        }

        /**
         * @param Deserialization $pool
         * @param                 $id
         * @param                 $v
         * @param ValueObject[]   $cache
         *
         * @return ValueObject
         */
        static function deserialize(Deserialization $pool, $id, $v, array &$cache) {
            if (isset($cache[$id]))
                return $cache[$id];

            $self            = new self;
            $self->className = $v['className'];
            $self->hash      = $v['hash'];
            $cache[$id]      = $self;

            foreach ($v['properties'] as $prop)
                $self->properties[] = Variable::deserialize($pool, $prop);

            return $self;
        }

    }

    class ValueResource extends Value {
        /**
         * @param resource $value
         *
         * @return self
         */
        static function introspect($value) {
            $self       = new self;
            $self->type = get_resource_type($value);
            $self->id   = (int)$value;

            return $self;
        }

        private $type;
        private $id;

        function renderImpl(PrettyPrinter $settings) { return $settings->text($this->type); }

        function serialize(Serialization $s) {
            return array(
                'type'     => 'resource',
                'resource' => array(
                    'resourceType' => $this->type,
                    'resourceId'   => $this->id,
                )
            );
        }

        static function deserialize($v) {
            $self       = new self;
            $self->type = $v['resourceType'];
            $self->id   = $v['resourceId'];

            return $self;
        }
    }

    class ValueString extends Value {
        private $string;

        /**
         * @param string $string
         */
        function __construct($string) { $this->string = $string; }

        function renderImpl(PrettyPrinter $settings) { return $settings->renderString($this->string); }

        function serialize(Serialization $s) { return array('type' => 'string', 'string' => $this->string); }
    }

    class ValueUnknown extends Value {
        function renderImpl(PrettyPrinter $settings) { return $settings->text('unknown type'); }

        function serialize(Serialization $s) { return array('type' => 'unknown'); }

        function __construct() {
            parent::__construct();
        }
    }

    class Deserialization {
        static function deserializeWhole($value) {
            $self          = new self;
            $self->objects = $value['objects'];
            $self->arrays  = $value['arrays'];

            return $self->deserialize($value['root']);
        }

        private $arrays = array();
        private $objects = array();

        /** @var ValueArray[] */
        private $arrayCache = array();
        /** @var ValueObject[] */
        private $objectCache = array();

        /**
         * @param array $v
         *
         * @throws \ErrorHandler\Exception
         * @return Value
         */
        function deserialize($v) {
            switch ($v['type']) {
                case 'object':
                    return $this->deserializeObject($v['object']);
                case 'float':
                    $value = $v['float'];
                    if ($value === 'INF')
                        return new ValueFloat(INF);
                    else if ($value === '-INF')
                        return new ValueFloat(-INF);
                    else if ($value === 'NAN')
                        return new ValueFloat(NAN);
                    else
                        return new ValueFloat((float)$value);
                case 'array':
                    return $this->deserializeArray($v['array']);
                case 'exception':
                    return ValueException::deserialize($this, $v['exception']);
                case 'resource':
                    return ValueResource::deserialize($v['resource']);
                case 'unknown':
                    return new ValueUnknown;
                case 'null':
                    return new ValueNull;
                case 'int':
                    return new ValueInt((int)$v['int']);
                case 'bool':
                    return new ValueBool((bool)$v['bool']);
                case 'string':
                    return new ValueString($v['string']);
                default:
                    throw new Exception("Unknown type: {$v['type']}");
            }
        }

        function deserializeObject($value) {
            return ValueObject::deserialize($this, $value, $this->objects[$value], $this->objectCache);
        }

        function deserializeArray($value) {
            return ValueArray::deserialize($this, $value, $this->arrays[$value], $this->arrayCache);
        }
    }

    class Serialization {
        static function serializeWhole(Value $v) {
            $self = new self;
            $root = $self->serialize($v);

            return array(
                'root'    => $root,
                'arrays'  => $self->arrays,
                'objects' => $self->objects,
            );
        }

        private $arrays = array();
        private $objects = array();

        function addArray(ValueArray $a) {
            $id = $a->id();

            if (!isset($this->arrays[$id])) {
                $this->arrays[$id] = true;
                $this->arrays[$id] = $a->serializeArray($this);
            }
        }

        function addObject(ValueObject $o) {
            $id = $o->id();

            if (!isset($this->objects[$id])) {
                $this->objects[$id] = true;
                $this->objects[$id] = $o->serializeObject($this);
            }
        }

        function serialize(Value $value) { return $value->serialize($this); }
    }
}
