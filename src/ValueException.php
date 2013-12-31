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
        $self->globals = ValueGlobalVariable::introspectGlobals($i);

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
        $self->globals = ValueGlobalVariable::mockGlobals($param);

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
    /** @var ValueGlobalVariable[]|null */
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
        $stack = array();

        foreach ($this->stack as $frame)
            $stack[] = $frame->toJsonValue($s);

        if ($this->locals !== null) {
            $locals = array();

            foreach ($this->locals as $local)
                $locals[] = $local->toJsonValue($s);
        } else {
            $locals = null;
        }

        if ($this->globals !== null) {
            $globals = array();

            foreach ($this->globals as $global)
                $globals[] = $global->toJsonValue($s);
        } else {
            $globals = null;
        }

        return array(
            'type'      => 'exception',
            'exception' => array(
                'className' => $this->className,
                'stack'     => $stack,
                'code'      => $this->code,
                'message'   => $this->message,
                'file'      => $this->file,
                'line'      => $this->line,
                'previous'  => $this->previous !== null ? $s->toJsonValue($this->previous) : null,
                'locals'    => $locals,
                'globals'   => $globals,
            ),
        );
    }

    static function fromJsonValueImpl(JsonSerialize $pool, array $v) {
        $self            = new self;
        $self->className = $v['className'];
        $self->code      = $v['code'];
        $self->message   = $v['message'];
        $self->file      = $v['file'];
        $self->line      = $v['line'];
        $self->previous  = $v['previous'] !== null ? self::fromJsonValueImpl($pool, $v['previous']) : null;

        foreach ($v['stack'] as $frame)
            $self->stack[] = ValueExceptionStackFrame::fromJsonValue($pool, $frame);

        if ($v['locals'] !== null) {
            $self->locals = array();

            foreach ($v['locals'] as $local)
                $self->locals[] = ValueVariable::fromJsonValue($pool, $local);
        }

        if ($v['globals'] !== null) {
            $self->globals = array();

            foreach ($v['globals'] as $global)
                $self->globals[] = ValueGlobalVariable::fromJsonValue($pool, $global);
        }

        return $self;
    }
}

class ValueVariable {
    static function fromJsonValue(JsonSerialize $pool, $prop) {
        return new self($prop['name'], $pool->fromJsonValue($prop['value']));
    }

    private $name;
    /** @var Value */
    private $value;

    /**
     * @param string $name
     * @param Value  $value
     */
    function __construct($name, Value $value) {
        $this->value = $value;
        $this->name  = $name;
    }

    function render(PrettyPrinter $settings) {
        return $this->renderPrefix($settings)->appendLines($settings->renderVariable($this->name));
    }

    function value() { return $this->value; }

    function name() { return $this->name; }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text();
    }

    function toJsonValue(JsonSerialize $s) {
        return array(
            'name'  => $this->name,
            'value' => $s->toJsonValue($this->value),
        );
    }
}

class ValueGlobalVariable extends ValueVariable {
    static function fromJsonValue(JsonSerialize $pool, $prop) {
        $self               = new self($prop['name'], $pool->fromJsonValue($prop['value']));
        $self->functionName = $prop['functionName'];
        $self->access       = $prop['access'];
        $self->isDefault    = $prop['isDefault'];
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
                $globals [] = new self($variableName, $i->introspectRef($globalValue));
            }
        }

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $property->setAccessible(true);

                $self            = new self($property->name, $i->introspect($property->getValue()));
                $self->className = $property->class;
                $self->access    = $i->propertyOrMethodAccess($property);
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

        $self            = new self('blahProperty', $null);
        $self->className = 'BlahClass';
        $self->access    = 'private';

        $globals[] = $self;

        $self               = new self('public', $null);
        $self->functionName = 'BlahAnotherClass';

        $globals[] = $self;

        $globals[] = new self('lol global', $null);

        $self               = new self('lolStatic', $null);
        $self->functionName = 'blahMethod';
        $self->className    = 'BlahYetAnotherClass';

        $globals[] = $self;

        $globals[] = new self('blahVariable', $null);

        return $globals;
    }

    private $className;
    private $functionName;
    private $access;
    private $isDefault;

    function renderPrefix(PrettyPrinter $settings) {
        if ($this->className !== null && $this->functionName !== null)
            return $settings->text("function $this->className::$this->functionName()::static ");

        if ($this->className !== null)
            return $settings->text("$this->access static $this->className::");

        if ($this->functionName !== null)
            return $settings->text("function $this->functionName()::static ");

        return $settings->text($this->isSuperGlobal() ? '' : 'global ');
    }

    function toJsonValue(JsonSerialize $s) {
        return array(
            'name'         => $this->name(),
            'value'        => $s->toJsonValue($this->value()),
            'className'    => $this->className,
            'functionName' => $this->functionName,
            'access'       => $this->access,
            'isDefault'    => $this->isDefault,
        );
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

class ValueExceptionStackFrame {
    static function fromJsonValue(JsonSerialize $pool, $frame) {
        $self               = new self;
        $self->functionName = $frame['functionName'];
        $self->isStatic     = $frame['isStatic'];
        $self->file         = $frame['file'];
        $self->line         = $frame['line'];
        $self->className    = $frame['className'];
        $self->object       = $frame['object'] !== null ? $pool->fromJsonValue($frame['object']) : null;

        if ($frame['args'] !== null) {
            $self->args = array();

            foreach ($frame['args'] as $arg)
                $self->args [] = $pool->fromJsonValue($arg);
        }

        return $self;
    }

    static function introspect(Introspection $i, array $frame) {
        $self               = new self;
        $self->functionName = array_get($frame, 'function');
        $self->file         = array_get($frame, 'file');
        $self->line         = array_get($frame, 'line');
        $self->className    = array_get($frame, 'class');
        $self->isStatic     = isset($frame['type']) ? $frame['type'] === '::' : null;
        $self->object       = isset($frame['object']) ? $i->introspectRef($frame['object']) : null;

        if (isset($frame['args'])) {
            $self->args = array();

            foreach ($frame['args'] as &$arg)
                $self->args[] = $i->introspectRef($arg);
        }

        return $self;
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

    private function __construct() { }

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
        if ($this->args !== null) {
            $args = array();

            foreach ($this->args as $arg)
                $args[] = $s->toJsonValue($arg);
        } else {
            $args = null;
        }

        return array(
            'functionName' => $this->functionName,
            'className'    => $this->className,
            'isStatic'     => $this->isStatic,
            'file'         => $this->file,
            'line'         => $this->line,
            'args'         => $args,
            'object'       => $this->object !== null ? $s->toJsonValue($this->object) : null,
        );
    }
}
