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
    static function introspectImpl(Introspection $i, \Exception $e) {
        $self          = self::introspectImplNoGlobals($i, $e);
        $self->globals = ValueVariable::introspectGlobals($i);

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
                $self->locals[] = new ValueVariable($name, $i->introspectRef($value));
        }

        foreach ($e instanceof ExceptionHasFullTrace ? $e->getFullTrace() : $e->getTrace() as $frame)
            $self->stack[] = ValueExceptionStackFrame::introspect($i, $frame);

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
        $self->locals    = array(new ValueVariable('lol', $param->introspect(8)),
                                 new ValueVariable('foo', $param->introspect('bar')));

        $self->stack   = ValueExceptionStackFrame::mock($param);
        $self->globals = ValueVariable::mockGlobals($param);

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
            $result['stack'][] = $frame->toJsonValue($s);

        if ($this->locals !== null) {
            $result['locals'] = array();

            foreach ($this->locals as $local)
                $result['locals'][] = $local->toJsonValue($s);
        }

        if ($this->globals !== null) {
            $result['globals'] = array();

            foreach ($this->globals as $global)
                $result['globals'][] = $global->toJsonValue($s);
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
            $self->stack[] = ValueExceptionStackFrame::fromJsonValue($pool, $frame);

        if (array_key_exists('previous', $v))
            $self->previous = self::fromJsonValueImpl($pool, $v['previous']);

        if (array_key_exists('locals', $v)) {
            $self->locals = array();

            foreach ($v['locals'] as $local)
                $self->locals[] = ValueVariable::fromJsonValue($pool, $local);
        }

        if (array_key_exists('globals', $v)) {
            $self->globals = array();

            foreach ($v['globals'] as $global)
                $self->globals[] = ValueVariable::fromJsonValue($pool, $global);
        }

        return $self;
    }
}

class ValueVariable {
    static function fromJsonValue(JsonSerialize $pool, $prop) {
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
     * @return ValueVariable[]
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

    function toJsonValue(JsonSerialize $s) {
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

class ValueExceptionStackFrame {
    static function fromJsonValue(JsonSerialize $pool, $frame) {
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
     * @return ValueExceptionStackFrame[]
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

    function toJsonValue(JsonSerialize $s) {
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
