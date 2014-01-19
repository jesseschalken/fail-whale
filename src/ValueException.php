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

class ValueException extends Value {
    /**
     * @param Introspection $i
     * @param \Exception    $x
     *
     * @return self
     */
    static function introspect(Introspection $i, &$x) {
    }

    static function mock(Introspection $param) {
        $self           = new self;
        $self->class    = 'MuhMockException';
        $self->message  = <<<'s'
This is a dummy exception message.

lololool
s;
        $self->code     = 'Dummy exception code';
        $self->location = ValueExceptionCodeLocation::mock('/path/to/muh/file', 9000);
        $self->locals   = ValueVariable::mockLocals($param);
        $self->stack    = ValueExceptionStackFrame::mock($param);
        $self->globals  = ValueExceptionGlobalState::mock($param);

        return $self;
    }

    static function fromJSON(JSONUnserialize $s, $x) {
        if ($x === null)
            return null;

        $self = new self;
        $self->schema()->fromJSON($s, $x[1]);

        return $self;
    }

    private $class;
    /** @var ValueExceptionStackFrame[] */
    private $stack = array();
    /** @var ValueVariable[]|null */
    private $locals;
    private $code;
    private $message;
    /** @var self|null */
    private $previous;
    /** @var ValueExceptionGlobalState|null */
    private $globals;
    /** @var ValueExceptionCodeLocation */
    private $location;

    function className() { return $this->class; }

    function code() { return $this->code; }

    function file() { return $this->location->file(); }

    function globals() { return $this->globals; }

    function line() { return $this->location->line(); }

    function locals() { return $this->locals; }

    function message() { return $this->message; }

    function previous() { return $this->previous; }

    function stack() { return $this->stack; }

    function sourceCode() { return $this->location->sourceCode(); }

    function toJSON(JSONSerialize $s) {
        return array('exception', $this->schema()->toJSON($s));
    }

    function setClass($class) { $this->class = $class; }

    /**
     * @param \ErrorHandler\ValueExceptionStackFrame[] $stack
     */
    function setStack($stack) { $this->stack = $stack; }

    /**
     * @param \ErrorHandler\ValueVariable[]|null $locals
     */
    function setLocals($locals) { $this->locals = $locals; }

    function setCode($code) { $this->code = $code; }

    function setMessage($message) { $this->message = $message; }

    /**
     * @param \ErrorHandler\ValueException|null $previous
     */
    function setPrevious($previous) { $this->previous = $previous; }

    /**
     * @param \ErrorHandler\ValueExceptionGlobalState|null $globals
     */
    function setGlobals($globals) { $this->globals = $globals; }

    /**
     * @param \ErrorHandler\ValueExceptionCodeLocation $location
     */
    function setLocation($location) { $this->location = $location; }

    private function schema() {
        $schema = new JSONSchema;
        $schema->bind('class', $this->class);
        $schema->bind('code', $this->code);
        $schema->bind('message', $this->message);
        $schema->bindObject('location', $this->location, function ($j, $v) {
            return ValueExceptionCodeLocation::fromJson($j, $v);
        });

        $schema->bindObject('previous', $this->previous, function ($j, $v) {
            return ValueException::fromJSON($j, $v);
        });

        $schema->bindObjectList('stack', $this->stack, function ($j, $v) {
            return ValueExceptionStackFrame::fromJSON($j, $v);
        });

        $schema->bindObjectList('locals', $this->locals, function ($j, $v) {
            return ValueVariable::fromJSON($j, $v);
        });

        $schema->bindObject('globals', $this->globals, function ($j, $v) {
            return ValueExceptionGlobalState::fromJSON($j, $v);
        });

        return $schema;
    }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitException($this); }
}

class ValueExceptionGlobalState implements JSONSerializable {
    static function fromJSON(JSONUnserialize $j, $v) {
        $self = new self;
        $self->schema()->fromJSON($j, $v);

        return $self;
    }

    static function mock(Introspection $i) {
        $self                   = new self;
        $self->staticProperties = ValueObjectPropertyStatic::mockStatic($i);
        $self->globalVariables  = ValueGlobalVariable::mockGlobals($i);
        $self->staticVariables  = ValueVariableStatic::mockStatics($i);

        return $self;
    }

    /** @var ValueObjectPropertyStatic[] */
    private $staticProperties = array();
    /** @var ValueGlobalVariable[] */
    private $globalVariables = array();
    /** @var ValueVariableStatic[] */
    private $staticVariables = array();

    function toJSON(JSONSerialize $s) { return $this->schema()->toJSON($s); }

    /** @return ValueVariable[] */
    function variables() {
        return array_merge($this->staticProperties,
                           $this->globalVariables,
                           $this->staticVariables);
    }

    function setStaticProperties($staticProperties) { $this->staticProperties = $staticProperties; }

    function setGlobalVariables($globalVariables) { $this->globalVariables = $globalVariables; }

    function setStaticVariables($staticVariables) { $this->staticVariables = $staticVariables; }

    private function schema() {
        $schema = new JSONSchema;

        $schema->bindObjectList('staticProperties', $this->staticProperties, function ($j, $v) {
            return ValueObjectPropertyStatic::fromJSON($j, $v);
        });

        $schema->bindObjectList('globalVariables', $this->globalVariables, function ($j, $v) {
            return ValueGlobalVariable::fromJSON($j, $v);
        });

        $schema->bindObjectList('staticVariables', $this->staticVariables, function ($j, $v) {
            return ValueVariableStatic::fromJSON($j, $v);
        });

        return $schema;
    }
}

class ValueExceptionCodeLocation implements JSONSerializable {
    /**
     * @param string        $file
     * @param int           $line
     * @param string[]|null $sourceCode
     *
     * @return ValueExceptionCodeLocation
     */
    static function mock($file, $line, array $sourceCode = null) {
        $self             = new self;
        $self->file       = $file;
        $self->line       = $line;
        $self->sourceCode = $sourceCode;

        return $self;
    }

    static function fromJson(JSONUnserialize $j, $x) {
        if ($x === null)
            return null;

        $self = new self;
        $self->schema()->fromJSON($j, $x);

        return $self;
    }

    private $line;
    private $file;
    private $sourceCode;

    function toJSON(JSONSerialize $s) {
        return $this->schema()->toJSON($s);
    }

    function render(PrettyPrinter $p) {
        return $p->text("$this->file:$this->line");
    }

    function sourceCode() { return $this->sourceCode; }

    function line() { return $this->line; }

    function file() { return $this->file; }

    function setSourceCode($sourceCode) { $this->sourceCode = $sourceCode; }

    function setLine($line) { $this->line = $line; }

    function setFile($file) { $this->file = $file; }

    private function schema() {
        $schema = new JSONSchema;
        $schema->bind('line', $this->line);
        $schema->bind('file', $this->file);
        $schema->bind('sourceCode', $this->sourceCode);

        return $schema;
    }
}

class ValueVariable implements JSONSerializable {
    static function mockLocals(Introspection $i) {
        $locals[] = self::introspect($i, 'lol', ref_new(8));
        $locals[] = self::introspect($i, 'foo', ref_new('bar'));

        return $locals;
    }

    static final function fromJSON(JSONUnserialize $s, $x) {
        $self = static::create();
        $self->schema()->fromJSON($s, $x);

        return $self;
    }

    /**
     * @param Introspection $i
     * @param string        $name
     * @param mixed         $value
     *
     * @return static
     */
    protected static function introspect(Introspection $i, $name, &$value) {
        $self        = static::create();
        $self->name  = $name;
        $self->value = $i->introspectRef($value);

        return $self;
    }

    /**
     * @return static
     */
    static protected function create() { return new self; }

    private $name;
    /** @var Value */
    private $value;

    function renderPrefix(PrettyPrinter $settings) { return $settings->text(); }

    function name() { return $this->name; }

    function value() { return $this->value; }

    function toJSON(JSONSerialize $s) { return $this->schema()->toJSON($s); }

    function setValue($value) { $this->value = $value; }

    function setName($name) { $this->name = $name; }

    protected function schema() {
        $schema = new JSONSchema;
        $schema->bind('name', $this->name);
        $schema->bindObject('value', $this->value, function ($j, $v) { return Value::fromJSON($j, $v); });

        return $schema;
    }
}

class ValueGlobalVariable extends ValueVariable {
    static function mockGlobals(Introspection $param) {
        $globals[] = self::introspect($param, 'lol global', ref_new());
        $globals[] = self::introspect($param, 'blahVariable', ref_new());

        return $globals;
    }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text($this->isSuperGlobal() ? '' : 'global ');
    }

    function isSuperGlobal() {
        $superGlobals = array(
            '_POST',
            '_GET',
            '_SESSION',
            '_COOKIE',
            '_FILES',
            '_REQUEST',
            '_ENV',
            '_SERVER',
        );

        return in_array($this->name(), $superGlobals, true);
    }
}

class ValueVariableStatic extends ValueVariable {
    /**
     * @param Introspection $i
     *
     * @return self[]
     */
    static function mockStatics(Introspection $i) {
        $self           = self::introspect($i, 'public', ref_new());
        $self->function = 'BlahAnotherClass';
        $globals[]      = $self;

        $self           = self::introspect($i, 'lolStatic', ref_new());
        $self->function = 'blahMethod';
        $self->class    = 'BlahYetAnotherClass';
        $globals[]      = $self;

        return $globals;
    }

    static protected function create() { return new self; }

    private $class, $function;

    function renderPrefix(PrettyPrinter $settings) {
        $function = $this->class === null ? "$this->function" : "$this->class::$this->function";

        return $settings->text("function $function()::static ");
    }

    function setClass($class) { $this->class = $class; }

    function setFunction($function) { $this->function = $function; }

    protected function schema() {
        $schema = parent::schema();
        $schema->bind('class', $this->class);
        $schema->bind('function', $this->function);

        return $schema;
    }
}

class ValueExceptionStackFrame implements JSONSerializable {
    /**
     * @param Introspection $param
     *
     * @return self[]
     */
    static function mock(Introspection $param) {
        $stack = array();

        $self           = new self;
        $self->function = 'aFunction';
        $self->args     = array($param->introspect(new DummyClass2));
        $self->location = ValueExceptionCodeLocation::mock('/path/to/muh/file', 1928);
        $self->object   = $param->introspect(new DummyClass1);
        $self->class    = 'DummyClass1';

        $stack[] = $self;

        $self           = new self;
        $self->function = 'aFunction';
        $self->args     = array($param->introspect(new DummyClass2));
        $self->location = ValueExceptionCodeLocation::mock('/path/to/muh/file', 1928);

        $stack[] = $self;

        return $stack;
    }

    static function fromJSON(JSONUnserialize $s, $x) {
        $self = new self;
        $self->schema()->fromJSON($s, $x);

        return $self;
    }

    private $class;
    private $function;
    /** @var Value[]|null */
    private $args;
    /** @var Value|null */
    private $object;
    private $isStatic;
    /** @var ValueExceptionCodeLocation */
    private $location;

    function subValues() {
        $x = array();

        if ($this->args !== null)
            foreach ($this->args as $arg)
                $x[] = $arg;

        if ($this->object !== null)
            $x[] = $this->object;

        return $x;
    }

    function file() { return $this->location === null ? null : $this->location->file(); }

    function line() { return $this->location === null ? null : $this->location->line(); }

    function location() { return $this->location === null ? '[internal function]' : "{$this->file()}:{$this->line()}"; }

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
                    ->append($this->function)
                    ->appendLines($this->renderArgs($settings));
    }

    function prefix(PrettyPrinter $settings) {
        if ($this->object !== null)
            return $this->object->render($settings)->append('->');

        if ($this->class !== null)
            return $settings->text($this->isStatic ? "$this->class::" : "$this->class->");

        return $settings->text();
    }

    function toJSON(JSONSerialize $s) { return $this->schema()->toJSON($s); }

    function setClass($class) { $this->class = $class; }

    function setFunction($function) { $this->function = $function; }

    function setArgs($args) { $this->args = $args; }

    function setObject($object) { $this->object = $object; }

    function setIsStatic($isStatic) { $this->isStatic = $isStatic; }

    function setLocation($location) { $this->location = $location; }

    private function schema() {
        $schema = new JSONSchema;
        $schema->bind('function', $this->function);
        $schema->bind('class', $this->class);
        $schema->bind('isStatic', $this->isStatic);
        $schema->bindObject('location', $this->location, function ($j, $v) {
            return ValueExceptionCodeLocation::fromJson($j, $v);
        });
        $schema->bindObject('object', $this->object, function ($j, $v) { return ValueObject::fromJSON($j, $v); });
        $schema->bindObjectList('args', $this->args, function ($j, $v) { return Value::fromJSON($j, $v); });

        return $schema;
    }
}
