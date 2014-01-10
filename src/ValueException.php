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
        $self          = self::introspectImplNoGlobals($i, $x);
        $self->globals = ValueExceptionGlobalState::introspect($i);

        return $self;
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

    static function fromJSON(JsonDeSerializationState $s, $x) {
        if ($x === null)
            return null;

        $self = new self;
        $self->schema()->fromJSON($s, $x[1]);

        return $self;
    }

    /**
     * @param Introspection $i
     * @param \Exception    $e
     *
     * @return ValueException|null
     */
    private static function introspectImplNoGlobals(Introspection $i, \Exception $e = null) {
        if ($e === null)
            return null;

        $locals = $e instanceof ExceptionHasLocalVariables ? $e->getLocalVariables() : null;
        $frames = $e instanceof ExceptionHasFullTrace ? $e->getFullTrace() : $e->getTrace();

        $self           = new self;
        $self->class    = get_class($e);
        $self->code     = $e->getCode();
        $self->message  = $e->getMessage();
        $self->location = ValueExceptionCodeLocation::introspect($i, $e->getFile(), $e->getLine());
        $self->locals   = ValueVariable::introspectLocals($i, $locals);
        $self->stack    = ValueExceptionStackFrame::introspectMany($i, $frames);
        $self->previous = self::introspectImplNoGlobals($i, $e->getPrevious());

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

    function subValues() {
        $x = parent::subValues();

        if ($this->locals !== null)
            foreach ($this->locals as $local)
                $x[] = $local->value();

        if ($this->globals !== null)
            foreach ($this->globals->variables() as $global)
                $x[] = $global->value();

        foreach ($this->stack as $frame)
            foreach ($frame->subValues() as $c)
                $x[] = $c;

        if ($this->previous !== null)
            foreach ($this->previous->subValues() as $c)
                $x[] = $c;

        return $x;
    }

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

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderExceptionWithGlobals($this);
    }

    function toJSON(JsonSerializationState $s) {
        return array('exception', $this->schema()->toJSON($s));
    }

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('class', $this->class);
        $schema->bindRef('code', $this->code);
        $schema->bindRef('message', $this->message);
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
}

class ValueExceptionGlobalState implements JsonSerializable {
    static function fromJSON(JsonDeSerializationState $j, $v) {
        $self = new self;
        $self->schema()->fromJSON($j, $v);

        return $self;
    }

    static function introspect(Introspection $i) {
        $self                   = new self;
        $self->staticProperties = ValueObjectPropertyStatic::introspectStaticProperties($i);
        $self->globalVariables  = ValueGlobalVariable::introspectGlobals($i);
        $self->staticVariables  = ValueVariableStatic::introspectStaticVariables($i);

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

    function toJSON(JsonSerializationState $s) { return $this->schema()->toJSON($s); }

    /** @return ValueVariable[] */
    function variables() {
        return array_merge($this->staticProperties,
                           $this->globalVariables,
                           $this->staticVariables);
    }

    private function schema() {
        $schema = new JsonSchemaObject;

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

    static function fromJson(JsonDeSerializationState $j, $x) {
        if ($x === null)
            return null;

        $self = new self;
        $self->schema()->fromJSON($j, $x);

        return $self;
    }

    static function introspect(/** @noinspection PhpUnusedParameterInspection */
        Introspection $i, $file, $line) {

        if ($file === null)
            return null;

        $self             = new self;
        $self->file       = $file;
        $self->line       = $line;
        $self->sourceCode = self::introspectSourceCode($file, $line);

        return $self;
    }

    /**
     * @param string|null $file
     * @param string|null $line
     *
     * @return string[]|null
     */
    private static function introspectSourceCode($file, $line) {
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

    private $line;
    private $file;
    private $sourceCode;

    function toJSON(JsonSerializationState $s) {
        return $this->schema()->toJSON($s);
    }

    function render(PrettyPrinter $p) {
        return $p->text("$this->file:$this->line");
    }

    function sourceCode() { return $this->sourceCode; }

    function line() { return $this->line; }

    function file() { return $this->file; }

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('line', $this->line);
        $schema->bindRef('file', $this->file);
        $schema->bindRef('sourceCode', $this->sourceCode);

        return $schema;
    }
}

class ValueVariable implements JsonSerializable {
    static function introspectLocals(Introspection $i, array $x = null) {
        if ($x === null)
            return null;

        $locals = array();

        foreach ($x as $name => &$value)
            $locals[] = self::introspect($i, $name, $value);

        return $locals;
    }

    static function mockLocals(Introspection $i) {
        $locals[] = self::introspect($i, 'lol', ref_new(8));
        $locals[] = self::introspect($i, 'foo', ref_new('bar'));

        return $locals;
    }

    static final function fromJSON(JsonDeSerializationState $s, $x) {
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
        $self->value = Value::introspect($i, $value);

        return $self;
    }

    /**
     * @return static
     */
    static protected function create() { return new self; }

    private $name;
    /** @var Value */
    private $value;

    function render(PrettyPrinter $settings) {
        return $this->renderPrefix($settings)->appendLines($settings->renderVariable($this->name));
    }

    function renderPrefix(PrettyPrinter $settings) { return $settings->text(); }

    function name() { return $this->name; }

    function value() { return $this->value; }

    function toJSON(JsonSerializationState $s) { return $this->schema()->toJSON($s); }

    protected function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('name', $this->name);
        $schema->bindValue('value', $this->value);

        return $schema;
    }
}

class ValueGlobalVariable extends ValueVariable {
    static function introspectGlobals(Introspection $i) {
        $globals = array();

        foreach ($GLOBALS as $variableName => &$globalValue) {
            if ($variableName !== 'GLOBALS') {
                $globals [] = self::introspect($i, $variableName, $globalValue);
            }
        }

        return $globals;
    }

    static function mockGlobals(Introspection $param) {
        $globals[] = self::introspect($param, 'lol global', ref_new());
        $globals[] = self::introspect($param, 'blahVariable', ref_new());

        return $globals;
    }

    static protected function create() { return new self; }

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

    /**
     * @param Introspection $i
     *
     * @return self[]
     */
    static function introspectStaticVariables(Introspection $i) {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $variableName => &$varValue) {
                    $self           = self::introspect($i, $variableName, $varValue);
                    $self->class    = $method->class;
                    $self->function = $method->getName();

                    $globals[] = $self;
                }
            }
        }

        foreach (get_defined_functions() as $section) {
            foreach ($section as $function) {
                $reflection      = new \ReflectionFunction($function);
                $staticVariables = $reflection->getStaticVariables();

                foreach ($staticVariables as $propertyName => &$varValue) {
                    $self           = self::introspect($i, $propertyName, $varValue);
                    $self->function = $function;

                    $globals[] = $self;
                }
            }
        }

        return $globals;
    }

    static protected function create() { return new self; }

    private $class, $function;

    function renderPrefix(PrettyPrinter $settings) {
        $function = $this->class === null ? "$this->function" : "$this->class::$this->function";

        return $settings->text("function $function()::static ");
    }

    protected function schema() {
        $schema = parent::schema();
        $schema->bindRef('class', $this->class);
        $schema->bindRef('function', $this->function);

        return $schema;
    }
}

class ValueExceptionStackFrame implements JsonSerializable {
    /**
     * @param Introspection $i
     * @param array         $frames
     *
     * @return self[]
     */
    static function introspectMany(Introspection $i, array $frames) {
        $result = array();

        foreach ($frames as $frame) {
            $self           = new self;
            $self->function = array_get($frame, 'function');
            $self->location = ValueExceptionCodeLocation::introspect($i, array_get($frame, 'file'), array_get($frame, 'line'));
            $self->class    = array_get($frame, 'class');
            $self->isStatic = isset($frame['type']) ? $frame['type'] === '::' : null;
            $self->object   = isset($frame['object']) ? ValueObject::introspect($i, $frame['object']) : null;

            if (isset($frame['args'])) {
                $self->args = array();

                foreach ($frame['args'] as $k => &$arg)
                    $self->args[$k] = Value::introspect($i, $arg);
            }

            $result[] = $self;
        }

        return $result;
    }

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

    static function fromJSON(JsonDeSerializationState $s, $x) {
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

    protected function __construct() { }

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

    function toJSON(JsonSerializationState $s) { return $this->schema()->toJSON($s); }

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('function', $this->function);
        $schema->bindRef('class', $this->class);
        $schema->bindRef('isStatic', $this->isStatic);
        $schema->bindObject('location', $this->location, function ($j, $v) {
            return ValueExceptionCodeLocation::fromJson($j, $v);
        });
        $schema->bindObject('object', $this->object, function ($j, $v) { return ValueObject::fromJSON($j, $v); });
        $schema->bindValueList('args', $this->args);

        return $schema;
    }
}
