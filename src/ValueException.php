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
    static function introspectImpl(Introspection $i, \Exception $x) {
        $self = new self;
        $self->introspectImplNoGlobals($i, $x);
        $self->globals = ValueExceptionGlobalState::introspect($i);

        return $self;
    }

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

    private function introspectImplNoGlobals(Introspection $i, \Exception $e) {
        $locals = $e instanceof ExceptionHasLocalVariables ? $e->getLocalVariables() : null;
        $frames = $e instanceof ExceptionHasFullTrace ? $e->getFullTrace() : $e->getTrace();

        $this->className = get_class($e);
        $this->code      = $e->getCode();
        $this->message   = $e->getMessage();
        $this->line      = $e->getLine();
        $this->file      = $e->getFile();
        $this->locals    = $locals !== null ? ValueVariable::introspectLocals($i, $locals) : null;
        $this->stack     = ValueExceptionStackFrame::introspectMany($i, $frames);

        if ($e->getPrevious() !== null) {
            $this->previous = new self;
            $this->previous->introspectImplNoGlobals($i, $e->getPrevious());
        }

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
        $self->locals    = ValueVariable::mockLocals($param);
        $self->stack     = ValueExceptionStackFrame::mock($param);
        $self->globals   = ValueExceptionGlobalState::mock($param);

        return $self;
    }

    private $className;
    /** @var ValueExceptionStackFrame[] */
    private $stack = array();
    /** @var ValueVariable[]|null */
    private $locals;
    private $code;
    private $message;
    /** @var self|null */
    private $previous;
    private $file;
    private $line;
    /** @var ValueExceptionGlobalState|null */
    private $globals;

    function className() { return $this->className; }

    function code() { return $this->code; }

    function file() { return $this->file; }

    function globals() { return $this->globals; }

    function line() { return $this->line; }

    function locals() { return $this->locals; }

    function message() { return $this->message; }

    function previous() { return $this->previous; }

    function stack() { return $this->stack; }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderExceptionWithGlobals($this);
    }

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('className', $this->className);
        $schema->bindRef('code', $this->code);
        $schema->bindRef('message', $this->message);
        $schema->bindRef('file', $this->file);
        $schema->bindRef('line', $this->line);

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

    function toJSON(JsonSerializationState $s) {
        return array('exception', $this->schema()->toJSON($s));
    }

    static function fromJSON(JsonDeSerializationState $s, $x) {
        if ($x === null)
            return null;

        $self = new self;
        $self->schema()->fromJSON($s, $x[1]);

        return $self;
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

    function toJSON(JsonSerializationState $s) {
        return $this->schema()->toJSON($s);
    }

    /** @return ValueVariable[] $vars */
    function variables() {
        return array_merge($this->staticProperties,
                           $this->globalVariables,
                           $this->staticVariables);
    }
}

class ValueVariable implements JsonSerializable {
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

    static function introspectLocals(Introspection $i, array $x) {
        $locals = array();

        foreach ($x as $name => &$value) {
            $locals[] = self::introspect($i, $name, $value);
        }

        return $locals;
    }

    static function mockLocals(Introspection $i) {
        $locals[] = self::introspect($i, 'lol', ref_new(8));
        $locals[] = self::introspect($i, 'foo', ref_new('bar'));

        return $locals;
    }

    private $name;
    /** @var Value */
    private $value;

    function render(PrettyPrinter $settings) {
        return $this->renderPrefix($settings)->appendLines($settings->renderVariable($this->name));
    }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text();
    }

    protected function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('name', $this->name);
        $schema->bindValue('value', $this->value);

        return $schema;
    }

    function name() { return $this->name; }

    function value() { return $this->value; }

    function toJSON(JsonSerializationState $s) {
        return $this->schema()->toJSON($s);
    }

    static final function fromJSON(JsonDeSerializationState $s, $x) {
        $self = static::create();
        $self->schema()->fromJSON($s, $x);

        return $self;
    }

    /**
     * @return static
     */
    static protected function create() { return new self; }
}

class ValueGlobalVariable extends ValueVariable {
    static protected function create() { return new self; }

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

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text($this->isSuperGlobal() ? '' : 'global ');
    }

    private function isSuperGlobal() {
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
    static function mockStatics(Introspection $i) {
        $self               = self::introspect($i, 'public', ref_new());
        $self->functionName = 'BlahAnotherClass';
        $globals[]          = $self;

        $self               = self::introspect($i, 'lolStatic', ref_new());
        $self->functionName = 'blahMethod';
        $self->className    = 'BlahYetAnotherClass';
        $globals[]          = $self;

        return $globals;
    }

    static protected function create() { return new self; }

    static function introspectStaticVariables(Introspection $i) {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getMethods() as $method) {
                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $variableName => &$varValue) {
                    $self               = self::introspect($i, $variableName, $varValue);
                    $self->className    = $method->class;
                    $self->functionName = $method->getName();

                    $globals[] = $self;
                }
            }
        }

        foreach (get_defined_functions() as $section) {
            foreach ($section as $function) {
                $reflection      = new \ReflectionFunction($function);
                $staticVariables = $reflection->getStaticVariables();

                foreach ($staticVariables as $propertyName => &$varValue) {
                    $self               = self::introspect($i, $propertyName, $varValue);
                    $self->functionName = $function;

                    $globals[] = $self;
                }
            }
        }

        return $globals;
    }

    private $className, $functionName;

    protected function schema() {
        $schema = parent::schema();
        $schema->bindRef('className', $this->className);
        $schema->bindRef('functionName', $this->functionName);

        return $schema;
    }

    function renderPrefix(PrettyPrinter $settings) {
        $function = $this->className === null ? "$this->functionName" : "$this->className::$this->functionName";

        return $settings->text("function $function()::static ");
    }
}

class ValueExceptionStackFrame implements JsonSerializable {
    static function introspectMany(Introspection $i, array $frames) {
        $result = array();

        foreach ($frames as $frame) {
            $self               = new self;
            $self->functionName = array_get($frame, 'function');
            $self->file         = array_get($frame, 'file');
            $self->line         = array_get($frame, 'line');
            $self->className    = array_get($frame, 'class');
            $self->isStatic     = isset($frame['type']) ? $frame['type'] === '::' : null;
            $self->object       = isset($frame['object']) ? $i->introspectRef($frame['object']) : null;

            if (isset($frame['args'])) {
                $self->args = array();

                foreach ($frame['args'] as $k => &$arg)
                    $self->args[$k] = $i->introspectRef($arg);
            }

            $result[] = $self;
        }

        return $result;
    }

    /**
     * @param Introspection $param
     *
     * @return ValueExceptionStackFrame[]
     */
    static function mock(Introspection $param) {
        $stack = array();

        $self               = new self;
        $self->functionName = 'aFunction';
        $self->args         = array($param->introspect(new DummyClass2));
        $self->file         = '/path/to/muh/file';
        $self->line         = 1928;
        $self->object       = $param->introspect(new DummyClass1);
        $self->className    = 'DummyClass1';

        $stack[] = $self;

        $self               = new self;
        $self->functionName = 'aFunction';
        $self->args         = array($param->introspect(new DummyClass2));
        $self->file         = '/path/to/muh/file';
        $self->line         = 1928;

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

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('functionName', $this->functionName);
        $schema->bindRef('className', $this->className);
        $schema->bindRef('isStatic', $this->isStatic);
        $schema->bindRef('file', $this->file);
        $schema->bindRef('line', $this->line);
        $schema->bindObject('object', $this->object, function ($j, $v) { return ValueObject::fromJSON($j, $v); });
        $schema->bindValueList('args', $this->args);

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->schema()->toJSON($s);
    }

    static function fromJSON(JsonDeSerializationState $s, $x) {
        $self = new self;
        $self->schema()->fromJSON($s, $x);

        return $self;
    }
}
