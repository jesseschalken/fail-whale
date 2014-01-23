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

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitException($this); }

    function location() { return $this->location; }
}

class ValueExceptionGlobalState {
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

    /** @return ValueVariable[] */
    function variables() {
        return array_merge($this->staticProperties,
                           $this->globalVariables,
                           $this->staticVariables);
    }

    function setStaticProperties($staticProperties) { $this->staticProperties = $staticProperties; }

    function setGlobalVariables($globalVariables) { $this->globalVariables = $globalVariables; }

    function setStaticVariables($staticVariables) { $this->staticVariables = $staticVariables; }

    function getStaticProperties() { return $this->staticProperties; }

    function getGlobalVariables() { return $this->globalVariables; }

    function getStaticVariables() { return $this->staticVariables; }
}

class ValueExceptionCodeLocation {
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

    private $line;
    private $file;
    private $sourceCode;

    function render(PrettyPrinter $p) {
        return $p->text("$this->file:$this->line");
    }

    function sourceCode() { return $this->sourceCode; }

    function line() { return $this->line; }

    function file() { return $this->file; }

    function setSourceCode($sourceCode) { $this->sourceCode = $sourceCode; }

    function setLine($line) { $this->line = $line; }

    function setFile($file) { $this->file = $file; }
}

class ValueVariable {
    static function mockLocals(Introspection $i) {
        $locals[] = self::introspect($i, 'lol', ref_new(8));
        $locals[] = self::introspect($i, 'foo', ref_new('bar'));

        return $locals;
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

    function setValue($value) { $this->value = $value; }

    function setName($name) { $this->name = $name; }
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

    /** @var string|null */
    private $class;
    /** @var string */
    private $function;

    function renderPrefix(PrettyPrinter $settings) {
        $function = $this->class === null ? "$this->function" : "$this->class::$this->function";

        return $settings->text("function $function()::static ");
    }

    function setClass($class) { $this->class = $class; }

    function setFunction($function) { $this->function = $function; }

    function getFunction() { return $this->function; }

    function getClass() { return $this->class; }
}

class ValueExceptionStackFrame {
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
        $self->object   = $param->introspectObject(new DummyClass1);
        $self->class    = 'DummyClass1';

        $stack[] = $self;

        $self           = new self;
        $self->function = 'aFunction';
        $self->args     = array($param->introspect(new DummyClass2));
        $self->location = ValueExceptionCodeLocation::mock('/path/to/muh/file', 1928);

        $stack[] = $self;

        return $stack;
    }

    function getArgs() { return $this->args; }

    function getClass() { return $this->class; }

    function getFunction() { return $this->function; }

    function getIsStatic() { return $this->isStatic; }

    function getLocation() { return $this->location; }

    function getObject() { return $this->object; }

    private $class;
    private $function;
    /** @var Value[]|null */
    private $args;
    /** @var ValueObject|null */
    private $object;
    private $isStatic;
    /** @var ValueExceptionCodeLocation */
    private $location;

    function file() { return $this->location === null ? null : $this->location->file(); }

    function line() { return $this->location === null ? null : $this->location->line(); }

    function location() { return $this->location === null ? '[internal function]' : "{$this->file()}:{$this->line()}"; }

    function setClass($class) { $this->class = $class; }

    function setFunction($function) { $this->function = $function; }

    function setArgs($args) { $this->args = $args; }

    function setObject($object) { $this->object = $object; }

    function setIsStatic($isStatic) { $this->isStatic = $isStatic; }

    function setLocation($location) { $this->location = $location; }
}
