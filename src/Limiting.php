<?php

namespace ErrorHandler;

class Limiter {
    public $maxArrayEntries = PHP_INT_MAX;
    public $maxObjectProperties = PHP_INT_MAX;
    public $maxStringLength = PHP_INT_MAX;
    public $maxStackFrames = PHP_INT_MAX;
    public $maxLocalVariables = PHP_INT_MAX;
    public $maxStaticProperties = PHP_INT_MAX;
    public $maxStaticVariables = PHP_INT_MAX;
    public $maxGlobalVariables = PHP_INT_MAX;
    public $maxFunctionArguments = PHP_INT_MAX;
}

class LimitedValue extends ValueFilter {
    private $settings;

    function __construct(Limiter $settings, ValueImpl $value) {
        parent::__construct($value);
        $this->settings = $settings;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        return parent::acceptVisitor(new LimitedValueVisitor($this->settings, $visitor));
    }
}

class LimitedValueVisitor extends ValueVisitorFilter {
    private $settings;

    function __construct(Limiter $settings, ValueVisitor $visitor) {
        parent::__construct($visitor);
        $this->settings = $settings;
    }

    function visitObject(ValueObject $object) {
        return parent::visitObject(new LimitedObject($this->settings, $object));
    }

    function visitArray(ValueArray $array) {
        return parent::visitArray(new LimitedArray($this->settings, $array));
    }

    function visitException(ValueException $exception) {
        return parent::visitException(new LimitedException($this->settings, $exception));
    }
}

class LimitedObject extends ValueObjectFilter {
    private $settings;

    function __construct(Limiter $settings, ValueObject $object) {
        parent::__construct($object);
        $this->settings = $settings;
    }

    function properties() {
        return array_slice(parent::properties(), 0, $this->settings->maxObjectProperties);
    }
}

class LimitedArray extends ValueArrayFilter {
    private $settings;

    function __construct(Limiter $settings, ValueArray $array) {
        parent::__construct($array);
        $this->settings = $settings;
    }

    function entries() {
        return array_slice(parent::entries(), 0, $this->settings->maxArrayEntries);
    }
}

class LimitedException extends ValueExceptionFilter {
    private $settings;

    function __construct(Limiter $settings, ValueException $exception) {
        parent::__construct($exception);
        $this->settings = $settings;
    }

    function previous() {
        $previous = parent::previous();

        return $previous instanceof ValueException ? new self($this->settings, $previous) : null;
    }

    function globals() {
        $globals = parent::globals();

        return $globals instanceof ValueGlobals ? new LimitedGlobals($this->settings, $globals) : null;
    }

    function locals() {
        $locals = parent::locals();

        return is_array($locals) ? array_slice($locals, 0, $this->settings->maxLocalVariables) : null;
    }

    function stack() {
        return array_slice(parent::stack(), 0, $this->settings->maxStackFrames);
    }
}

class LimitedGlobals extends ValueGlobalsFilter {
    private $settings;

    function __construct(Limiter $settings, ValueGlobals $globals) {
        parent::__construct($globals);
        $this->settings = $settings;
    }

    function staticProperties() {
        return array_slice(parent::staticProperties(), 0, $this->settings->maxStaticProperties);
    }

    function staticVariables() {
        return array_slice(parent::staticVariables(), 0, $this->settings->maxStaticVariables);
    }

    function globalVariables() {
        return array_slice(parent::globalVariables(), 0, $this->settings->maxGlobalVariables);
    }
}

class LimitedStackFrame extends ValueStackFrameFilter {
    private $settings;

    function __construct(Limiter $settings, ValueStackFrame $stackFrame) {
        parent::__construct($stackFrame);
        $this->settings = $settings;
    }

    function arguments() {
        $arguments = parent::arguments();

        return is_array($arguments) ? array_slice($arguments, 0, $this->settings->maxFunctionArguments) : null;
    }
}
