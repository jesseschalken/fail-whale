<?php

namespace ErrorHandler;

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

    function introspect(Introspection $i, array &$array) {
        foreach ($this->entries as $entry)
            if ($entry->equals($array))
                return $entry->result();

        return ValueArray::introspectImpl($i, $array, $this);
    }

    function insert(&$array, ValueArray $result) {
        $this->entries[] = new ArrayCacheEntry($array, $result);
    }
}

class ArrayCacheEntry {
    /** @var array */
    private $array;
    /** @var ValueArray */
    private $result;

    function __construct(array &$array, ValueArray $result) {
        $this->array  =& $array;
        $this->result = $result;
    }

    function equals(array &$array) { return ref_equal($this->array, $array); }

    function result() { return $this->result; }
}

class ObjectCache {
    /** @var ValueObject[] */
    private $results = array();
    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    private $objects = array();

    /**
     * @param Introspection $i
     * @param object        $object
     *
     * @return ValueObject
     */
    function introspect(Introspection $i, $object) {
        $hash = spl_object_hash($object);

        if (array_key_exists($hash, $this->results))
            return $this->results[$hash];

        return ValueObject::introspectImpl($i, $hash, $object, $this);
    }

    function insert($object, $hash, ValueObject $result) {
        $this->objects[$hash] = $object;
        $this->results[$hash] = $result;
    }
}

class Introspection {
    private $objectCache, $arrayCache;

    function __construct() {
        $this->objectCache = new ObjectCache;
        $this->arrayCache  = new ArrayCache;
    }

    /**
     * @param \ReflectionProperty|\ReflectionMethod $property
     *
     * @throws \Exception
     * @return string
     */
    function propertyOrMethodAccess($property) {
        if ($property->isPublic())
            return 'public';
        if ($property->isPrivate())
            return 'private';
        if ($property->isProtected())
            return 'protected';

        throw new \Exception("This thing is not protected, public, nor private? Huh?");
    }

    function introspectException(\Exception $e) {
        return ValueException::introspectImpl($this, $e);
    }

    function introspect($x) {
        return $this->introspectRef($x);
    }

    function introspectRef(&$x) {
        if (is_string($x))
            return new ValueString($x);

        if (is_int($x))
            return new ValueInt($x);

        if (is_bool($x))
            return new ValueBool($x);

        if (is_null($x))
            return new ValueNull($x);

        if (is_float($x))
            return new ValueFloat($x);

        if (is_array($x))
            return $this->arrayCache->introspect($this, $x);

        if (is_object($x))
            return $this->objectCache->introspect($this, $x);

        if (is_resource($x))
            return ValueResource::introspectImpl($x);

        return new ValueUnknown;
    }
}

abstract class Value {
    static function introspect($x) {
        return self::i()->introspect($x);
    }

    static function introspectRef(&$x) {
        return self::i()->introspectRef($x);
    }

    static function introspectException(\Exception $e) {
        return self::i()->introspectException($e);
    }

    static function fromJsonValue($x) {
        return JsonSerialize::fromJsonWhole($x);
    }

    static function fromJson($json) {
        return self::fromJsonValue(JSON::parse($json));
    }

    private static function i() { return new Introspection; }

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

    abstract function toJsonValueImpl(JsonSerialize $s);

    final function toJsonValue() {
        return JsonSerialize::toJsonWhole($this);
    }

    final function toJson() {
        return JSON::stringify($this->toJsonValue());
    }

    function toJsonFromJson() {
        return self::fromJson($this->toJson());
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
     * @param Introspection $i
     * @param array         $array
     * @param ArrayCache    $cache
     *
     * @return ValueArray
     */
    static function introspectImpl(Introspection $i, array &$array, ArrayCache $cache) {
        $self = new self;
        $cache->insert($array, $self);

        $self->isAssociative = array_is_associative($array);

        foreach ($array as $k => &$v)
            $self->entries[] = new ArrayEntry($i->introspect($k), $i->introspectRef($v));

        return $self;
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

    function toJsonValueImpl(JsonSerialize $s) {
        return array('type' => 'array', 'array' => $s->addArray($this));
    }

    function serializeArray(JsonSerialize $s) {
        $result = array(
            'isAssociative' => $this->isAssociative,
            'entries'       => array(),
        );

        foreach ($this->entries as $entry)
            $result['entries'][] = array(
                'key'   => $s->toJsonValue($entry->key()),
                'value' => $s->toJsonValue($entry->value()),
            );

        return $result;
    }

    static function fromJsonValueImpl(JsonSerialize $pool, $index, array $v) {
        $self                = new self;
        $self->isAssociative = $v['isAssociative'];

        $pool->insertArray($index, $self);

        foreach ($v['entries'] as $entry)
            $self->entries[] = new ArrayEntry($pool->fromJsonValue($entry['key']),
                                              $pool->fromJsonValue($entry['value']));

        return $self;
    }
}

class ArrayEntry {
    private $key, $value;

    function __construct(Value $key, Value $value) {
        $this->key   = $key;
        $this->value = $value;
    }

    function key() { return $this->key; }

    function value() { return $this->value; }
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

    function toJsonValueImpl(JsonSerialize $s) { return array('type' => 'bool', 'bool' => $this->bool); }
}

class ValueException extends Value {
    static function introspectImpl(Introspection $i, \Exception $e) {
        $self          = self::introspectImplNoGlobals($i, $e);
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

    private static function introspectImplNoGlobals(Introspection $i, \Exception $e) {
        $self            = new self;
        $self->className = get_class($e);
        $self->code      = $e->getCode();
        $self->message   = $e->getMessage();
        $self->line      = $e->getLine();
        $self->file      = $e->getFile();

        if ($e->getPrevious() !== null)
            $self->previous = self::introspectImplNoGlobals($i, $e->getPrevious());

        if ($e instanceof ExceptionHasLocalVariables && $e->getLocalVariables() !== null) {
            $self->locals = array();

            $locals = $e->getLocalVariables();
            foreach ($locals as $name => &$value)
                $self->locals[] = new Variable($name, $i->introspectRef($value));
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
        $self->locals    = array(new Variable('lol', $param->introspect(8)),
                                 new Variable('foo', $param->introspect('bar')));

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

    function toJsonValueImpl(JsonSerialize $s) {
        $result = array(
            'className' => $this->className,
            'stack'     => array(),
            'code'      => $this->code,
            'message'   => $this->message,
            'file'      => $this->file,
            'line'      => $this->line,
        );

        if ($this->previous !== null)
            $result['previous'] = $s->toJsonValue($this->previous);

        foreach ($this->stack as $frame)
            $result['stack'][] = $frame->serialize($s);

        if ($this->locals !== null) {
            $result['locals'] = array();

            foreach ($this->locals as $local)
                $result['locals'][] = $local->serialize($s);
        }

        if ($this->globals !== null) {
            $result['globals'] = array();

            foreach ($this->globals as $global)
                $result['globals'][] = $global->serialize($s);
        }

        return array(
            'type'      => 'exception',
            'exception' => $result,
        );
    }

    static function fromJsonValueImpl(JsonSerialize $pool, array $v) {
        $self            = new self;
        $self->className = $v['className'];
        $self->code      = $v['code'];
        $self->message   = $v['message'];
        $self->file      = $v['file'];
        $self->line      = $v['line'];

        foreach ($v['stack'] as $frame)
            $self->stack[] = FunctionCall::deserialize($pool, $frame);

        if (array_key_exists('previous', $v))
            $self->previous = self::fromJsonValueImpl($pool, $v['previous']);

        if (array_key_exists('locals', $v)) {
            $self->locals = array();

            foreach ($v['locals'] as $local)
                $self->locals[] = Variable::deserialize($pool, $local);
        }

        if (array_key_exists('globals', $v)) {
            $self->globals = array();

            foreach ($v['globals'] as $global)
                $self->globals[] = Variable::deserialize($pool, $global);
        }

        return $self;
    }
}

class Variable {
    static function deserialize(JsonSerialize $pool, $prop) {
        $self               = new self($prop['name'], $pool->fromJsonValue($prop['value']));
        $self->functionName = array_get($prop, 'functionName');
        $self->access       = array_get($prop, 'access');
        $self->isGlobal     = array_get($prop, 'isGlobal');
        $self->isStatic     = array_get($prop, 'isStatic');
        $self->isDefault    = array_get($prop, 'isDefault');
        $self->className    = array_get($prop, 'className');

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
                $self           = new self($variableName, $i->introspectRef($globalValue));
                $self->isGlobal = true;

                $globals [] = $self;
            }
        }

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $property->setAccessible(true);

                $self            = new self($property->name, $i->introspect($property->getValue()));
                $self->className = $property->class;
                $self->access    = $i->propertyOrMethodAccess($property);
                $self->isStatic  = true;
                $self->isDefault = $property->isDefault();

                $globals[] = $self;
            }

            foreach ($reflection->getMethods() as $method) {
                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $variableName => &$varValue) {
                    $self               = new self($variableName, $i->introspectRef($varValue));
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
                    $self               = new self($propertyName, $i->introspectRef($varValue));
                    $self->functionName = $function;

                    $globals[] = $self;
                }
            }
        }

        return $globals;
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

                $self            = new self($property->name, $i->introspect($property->getValue($object)));
                $self->className = $property->class;
                $self->access    = $i->propertyOrMethodAccess($property);
                $self->isDefault = $property->isDefault();

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

        $null = $param->introspect(null);

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
    private $isGlobal;
    private $isStatic;
    private $isDefault;

    /**
     * @param string $name
     * @param Value  $value
     */
    function __construct($name, Value $value) {
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

    function serialize(JsonSerialize $s) {
        $result = array(
            'name'  => $this->name,
            'value' => $s->toJsonValue($this->value),
        );

        array_set($result, 'className', $this->className);
        array_set($result, 'functionName', $this->functionName);
        array_set($result, 'access', $this->access);
        array_set($result, 'isGlobal', $this->isGlobal);
        array_set($result, 'isStatic', $this->isStatic);
        array_set($result, 'isDefault', $this->isDefault);

        return $result;
    }

    function value() { return $this->value; }
}

class FunctionCall {
    static function deserialize(JsonSerialize $pool, $frame) {
        $self            = new self($frame['functionName']);
        $self->isStatic  = array_get($frame, 'isStatic');
        $self->file      = array_get($frame, 'file');
        $self->line      = array_get($frame, 'line');
        $self->className = array_get($frame, 'className');

        if (array_key_exists('object', $frame))
            $self->object = $pool->fromJsonValue($frame['object']);

        if (array_key_exists('args', $frame)) {
            $self->args = array();

            foreach ($frame['args'] as $arg)
                $self->args [] = $pool->fromJsonValue($arg);
        }

        return $self;
    }

    static function introspect(Introspection $i, array $frame) {
        $self            = new self($frame['function']);
        $self->file      = array_get($frame, 'file');
        $self->line      = array_get($frame, 'line');
        $self->className = array_get($frame, 'class');

        if (array_key_exists('type', $frame))
            $self->isStatic = $frame['type'] === '::';

        if (array_key_exists('args', $frame)) {
            $self->args = array();

            foreach ($frame['args'] as &$arg)
                $self->args[] = $i->introspectRef($arg);
        }

        if (array_key_exists('object', $frame))
            $self->object = $i->introspectRef($frame['object']);

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
        $self->args      = array($param->introspect(new DummyClass2));
        $self->file      = '/path/to/muh/file';
        $self->line      = 1928;
        $self->object    = $param->introspect(new DummyClass1);
        $self->className = 'DummyClass1';

        $stack[] = $self;

        $self       = new self('aFunction');
        $self->args = array($param->introspect(new DummyClass2));
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

    function serialize(JsonSerialize $s) {
        $result = array('functionName' => $this->functionName);

        if ($this->args !== null) {
            $result['args'] = array();

            foreach ($this->args as $arg)
                $result['args'][] = $s->toJsonValue($arg);
        }

        if ($this->object !== null)
            $result['object'] = $s->toJsonValue($this->object);

        array_set($result, 'className', $this->className);
        array_set($result, 'isStatic', $this->isStatic);
        array_set($result, 'file', $this->file);
        array_set($result, 'line', $this->line);

        return $result;
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

    function toJsonValueImpl(JsonSerialize $s) { return array('type' => 'float', 'float' => $this->toPHP()); }

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

    function toJsonValueImpl(JsonSerialize $s) { return array('type' => 'int', 'int' => $this->int); }
}

class ValueNull extends Value {
    function renderImpl(PrettyPrinter $settings) { return $settings->text('null'); }

    function toJsonValueImpl(JsonSerialize $s) { return array('type' => 'null'); }

    function __construct() {
        parent::__construct();
    }
}

class ValueObject extends Value {
    /**
     * @param Introspection $i
     * @param string        $hash
     * @param object        $object
     * @param ObjectCache   $cache
     *
     * @return ValueObject
     */
    static function introspectImpl(Introspection $i, $hash, $object, ObjectCache $cache) {
        $self = new self;
        $cache->insert($object, $hash, $self);

        $self->hash       = $hash;
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

    function toJsonValueImpl(JsonSerialize $s) {
        return array('type' => 'object', 'object' => $s->addObject($this));
    }

    function serializeObject(JsonSerialize $s) {
        $result = array(
            'className'  => $this->className,
            'hash'       => $this->hash,
            'properties' => array(),
        );

        foreach ($this->properties as $prop)
            $result['properties'][] = $prop->serialize($s);

        return $result;
    }

    static function fromJsonValueImpl(JsonSerialize $pool, $index, array $v) {
        $self            = new self;
        $self->className = $v['className'];
        $self->hash      = $v['hash'];

        $pool->insertObject($index, $self);

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
    static function introspectImpl($value) {
        $self       = new self;
        $self->type = get_resource_type($value);
        $self->id   = (int)$value;

        return $self;
    }

    private $type;
    private $id;

    function renderImpl(PrettyPrinter $settings) { return $settings->text($this->type); }

    function toJsonValueImpl(JsonSerialize $s) {
        return array(
            'type'     => 'resource',
            'resource' => array(
                'resourceType' => $this->type,
                'resourceId'   => $this->id,
            ),
        );
    }

    static function fromJsonValueImpl(array $v) {
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

    function toJsonValueImpl(JsonSerialize $s) { return array('type' => 'string', 'string' => $this->string); }
}

class ValueUnknown extends Value {
    function renderImpl(PrettyPrinter $settings) { return $settings->text('unknown type'); }

    function toJsonValueImpl(JsonSerialize $s) { return array('type' => 'unknown'); }

    function __construct() {
        parent::__construct();
    }
}

class JsonSerialize {
    static function fromJsonWhole(array $value) {
        $self                    = new self;
        $self->serializedObjects = $value['objects'];
        $self->serializedArrays  = $value['arrays'];

        return $self->fromJsonValue($value['root']);
    }

    static function toJsonWhole(Value $v) {
        $self = new self;
        $root = $self->toJsonValue($v);

        return array(
            'root'    => $root,
            'arrays'  => $self->serializedArrays,
            'objects' => $self->serializedObjects,
        );
    }

    /** @var mixed[] */
    private $serializedArrays = array();
    /** @var mixed[] */
    private $serializedObjects = array();

    /** @var ValueArray[] */
    private $mapIndexToArray = array();
    /** @var ValueObject[] */
    private $mapIndexToObject = array();

    /** @var int[] */
    private $mapArrayToIndex = array();
    /** @var int[] */
    private $mapObjectToIndex = array();

    private function __construct() { }

    /**
     * @param array $v
     *
     * @throws Exception
     * @return Value
     */
    function fromJsonValue(array $v) {
        switch ($v['type']) {
            case 'object':
                $index = $v['object'];

                if (isset($this->mapIndexToObject[$index]))
                    return $this->mapIndexToObject[$index];

                return ValueObject::fromJsonValueImpl($this, $index, $this->serializedObjects[$index]);
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
                $index = $v['array'];

                if (isset($this->mapIndexToArray[$index]))
                    return $this->mapIndexToArray[$index];

                return ValueArray::fromJsonValueImpl($this, $index, $this->serializedArrays[$index]);
            case 'exception':
                return ValueException::fromJsonValueImpl($this, $v['exception']);
            case 'resource':
                return ValueResource::fromJsonValueImpl($v['resource']);
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

    function toJsonValue(Value $value) {
        return $value->toJsonValueImpl($this);
    }

    function insertObject($index, ValueObject $self) {
        $this->mapIndexToObject[$index]      = $self;
        $this->mapObjectToIndex[$self->id()] = $index;
    }

    function insertArray($index, ValueArray $self) {
        $this->mapIndexToArray[$index]      = $self;
        $this->mapArrayToIndex[$self->id()] = $index;
    }

    function addArray(ValueArray $array) {
        if (isset($this->mapArrayToIndex[$array->id()]))
            return $this->mapArrayToIndex[$array->id()];

        $index = count($this->mapArrayToIndex);

        $this->insertArray($index, $array);

        $this->serializedArrays[$index] = $array->serializeArray($this);

        return $index;
    }

    function addObject(ValueObject $object) {
        if (isset($this->mapObjectToIndex[$object->id()]))
            return $this->mapObjectToIndex[$object->id()];

        $index = count($this->mapObjectToIndex);

        $this->insertObject($index, $object);

        $this->serializedObjects[$index] = $object->serializeObject($this);

        return $index;
    }
}


