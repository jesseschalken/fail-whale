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
    function introspectImpl(Introspection $i, &$x) {
        $this->introspectImplNoGlobals($i, $x);
        $this->globals = ValueVariable::introspectGlobals($i);
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
        $self->globals   = ValueVariable::mockGlobals($param);

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
    /** @var ValueVariable[]|null */
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

    function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('className', $this->className);
        $schema->bindRef('code', $this->code);
        $schema->bindRef('message', $this->message);
        $schema->bindRef('file', $this->file);
        $schema->bindRef('line', $this->line);
        $schema->bindObject('previous', $this->previous, function () { return new ValueException; }, true);
        $schema->bindObjectList('stack', $this->stack, function () { return new ValueExceptionStackFrame; });
        $schema->bindObjectList('locals', $this->locals, function () { return new ValueVariable; });
        $schema->bindObjectList('globals', $this->globals, function () { return new ValueVariable; });

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        return array('exception', $this->schema()->toJSON($s));
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->schema()->fromJSON($s, $x[1]);
    }
}

class ValueVariable implements JsonSerializable {
    static function introspectLocals(Introspection $i, array $x) {
        $locals = array();

        foreach ($x as $name => &$value) {
            $local        = new self;
            $local->name  = $name;
            $local->value = $i->introspectRef($value);
            $locals[]     = $local;
        }

        return $locals;
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
                $self           = new self;
                $self->name     = $variableName;
                $self->value    = $i->introspectRef($globalValue);
                $self->isGlobal = true;

                $globals [] = $self;
            }
        }

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $property->setAccessible(true);

                $self            = new self;
                $self->name      = $property->name;
                $self->value     = $i->introspect($property->getValue());
                $self->className = $property->class;
                $self->access    = $i->propertyOrMethodAccess($property);
                $self->isStatic  = true;
                $self->isDefault = $property->isDefault();

                $globals[] = $self;
            }

            foreach ($reflection->getMethods() as $method) {
                $staticVariables = $method->getStaticVariables();

                foreach ($staticVariables as $variableName => &$varValue) {
                    $self               = new self;
                    $self->name         = $variableName;
                    $self->value        = $i->introspectRef($varValue);
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
                    $self               = new self;
                    $self->name         = $propertyName;
                    $self->value        = $i->introspectRef($varValue);
                    $self->functionName = $function;

                    $globals[] = $self;
                }
            }
        }

        return $globals;
    }

    /**
     * @param Introspection $i
     * @param object        $object
     *
     * @return self[]
     */
    static function introspectObjectProperties(Introspection $i, $object) {
        $properties = array();

        for ($reflection = new \ReflectionObject($object);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || $property->class !== $reflection->name)
                    continue;

                $property->setAccessible(true);

                $self            = new self;
                $self->name      = $property->name;
                $self->value     = $i->introspect($property->getValue($object));
                $self->className = $property->class;
                $self->access    = $i->propertyOrMethodAccess($property);
                $self->isDefault = $property->isDefault();

                $properties[] = $self;
            }
        }

        return $properties;
    }

    /**
     * @param Introspection $param
     *
     * @return self[]
     */
    static function mockGlobals(Introspection $param) {
        //  private static BlahClass::$blahProperty                       = null;
        //  function BlahAnotherClass()::static $public                   = null;
        //  global ${"lol global"}                                        = null;
        //  function BlahYetAnotherClass::blahMethod()::static $lolStatic = null;
        //  global $blahVariable                                          = null;

        $null = $param->introspect(null);

        $globals = array();

        $self            = new self;
        $self->name      = 'blahProperty';
        $self->value     = $null;
        $self->className = 'BlahClass';
        $self->access    = 'private';
        $self->isStatic  = true;

        $globals[] = $self;

        $self               = new self;
        $self->name         = 'public';
        $self->value        = $null;
        $self->functionName = 'BlahAnotherClass';

        $globals[] = $self;

        $self           = new self;
        $self->name     = 'lol global';
        $self->value    = $null;
        $self->isGlobal = true;

        $globals[] = $self;

        $self               = new self;
        $self->name         = 'lolStatic';
        $self->value        = $null;
        $self->functionName = 'blahMethod';
        $self->className    = 'BlahYetAnotherClass';
        $self->isStatic     = true;

        $globals[] = $self;

        $self           = new self;
        $self->name     = 'blahVariable';
        $self->value    = $null;
        $self->isGlobal = true;

        $globals[] = $self;

        return $globals;
    }

    static function mockLocals(Introspection $i) {
        $locals = array();

        $self        = new self;
        $self->name  = 'lol';
        $self->value = $i->introspect(8);

        $locals[] = $self;

        $self        = new self;
        $self->name  = 'foo';
        $self->value = $i->introspect('bar');

        $locals[] = $self;

        return $locals;
    }

    private $name;
    /** @var Value */
    private $value;
    private $className;
    private $functionName;
    private $access;
    private $isGlobal;
    private $isStatic;
    private $isDefault;

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

    function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('name', $this->name);
        $schema->bindValue('value', $this->value);
        $schema->bindRef('className', $this->className);
        $schema->bindRef('functionName', $this->functionName);
        $schema->bindRef('access', $this->access);
        $schema->bindRef('isGlobal', $this->isGlobal);
        $schema->bindRef('isStatic', $this->isStatic);
        $schema->bindRef('isDefault', $this->isDefault);

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->schema()->toJSON($s);
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->schema()->fromJSON($s, $x);
    }

    function value() { return $this->value; }
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

    function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('functionName', $this->functionName);
        $schema->bindRef('className', $this->className);
        $schema->bindRef('isStatic', $this->isStatic);
        $schema->bindRef('file', $this->file);
        $schema->bindRef('line', $this->line);
        $schema->bindObject('object', $this->object, function () { return new ValueObject; }, true);
        $schema->bindValueList('args', $this->args);

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->schema()->toJSON($s);
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->schema()->fromJSON($s, $x);
    }
}
