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

class LimitedThing {
    protected $settings;

    function __construct(Limiter $settings) {
        $this->settings = $settings;
    }
}

class LimitedValue extends LimitedThing implements ValueImpl {
    private $value;

    function __construct(Limiter $settings, ValueImpl $value) {
        parent::__construct($settings);
        $this->value = $value;
    }

    function acceptVisitor(ValueVisitor $visitor) {
        return $this->value->acceptVisitor(new LimitedValueVisitor($this->settings, $visitor));
    }
}

class LimitedValueVisitor extends LimitedThing implements ValueVisitor {
    private $visitor;

    function __construct(Limiter $settings, ValueVisitor $visitor) {
        parent::__construct($settings);
        $this->visitor = $visitor;
    }

    function visitObject(ValueObject $object) {
        return $this->visitor->visitObject(new LimitedObject($this->settings, $object));
    }

    function visitArray(ValueArray $array) {
        return $this->visitor->visitArray(new LimitedArray($this->settings, $array));
    }

    function visitException(ValueException $exception) {
        return $this->visitor->visitException(new LimitedException($this->settings, $exception));
    }

    function visitString(ValueString $string) {
        return $this->visitor->visitString(new LimitedString($this->settings, $string));
    }

    function visitInt($int) { return $this->visitor->visitInt($int); }

    function visitNull() { return $this->visitor->visitNull(); }

    function visitUnknown() { return $this->visitor->visitUnknown(); }

    function visitFloat($float) { return $this->visitor->visitFloat($float); }

    function visitResource(ValueResource $resource) { return $this->visitor->visitResource($resource); }

    function visitBool($bool) { return $this->visitor->visitBool($bool); }
}

class LimitedString extends LimitedThing implements ValueString {
    private $string;

    function __construct(Limiter $settings, ValueString $string) {
        parent::__construct($settings);
        $this->string = $string;
    }

    function id() { return $this->string->id(); }

    function string() {
        $string = $this->string->string();
        $string = substr($string, 0, $this->settings->maxStringLength);

        return $string;
    }

    function length() { return $this->string->length(); }
}

class LimitedObject extends LimitedThing implements ValueObject {
    private $object;

    function __construct(Limiter $settings, ValueObject $object) {
        parent::__construct($settings);
        $this->object = $object;
    }

    function properties() {
        $properties = $this->object->properties();
        $properties = array_slice($properties, 0, $this->settings->maxObjectProperties);

        $result = array();

        foreach ($properties as $property)
            $result[] = new LimitedObjectProperty($this->settings, $property);

        return $result;
    }

    function className() { return $this->object->className(); }

    function hash() { return $this->object->hash(); }

    function id() { return $this->object->id(); }

    function numProperties() { return $this->object->numProperties(); }
}

class LimitedArray extends LimitedThing implements ValueArray {
    private $array;

    function __construct(Limiter $settings, ValueArray $array) {
        parent::__construct($settings);
        $this->array = $array;
    }

    function entries() {
        $entries = $this->array->entries();
        $entries = array_slice($entries, 0, $this->settings->maxArrayEntries);

        $result = array();

        foreach ($entries as $entry)
            $result[] = new LimitedArrayEntry($this->settings, $entry);

        return $result;
    }

    function isAssociative() { return $this->array->isAssociative(); }

    function id() { return $this->array->id(); }

    function numEntries() { return $this->array->numEntries(); }
}

class LimitedArrayEntry extends LimitedThing implements ValueArrayEntry {
    private $entry;

    function __construct(Limiter $settings, ValueArrayEntry $entry) {
        parent::__construct($settings);
        $this->entry = $entry;
    }

    function key() { return new LimitedValue($this->settings, $this->entry->key()); }

    function value() { return new LimitedValue($this->settings, $this->entry->value()); }
}

class LimitedException extends LimitedThing implements ValueException {
    private $exception;

    function __construct(Limiter $settings, ValueException $exception) {
        parent::__construct($settings);
        $this->exception = $exception;
    }

    function previous() {
        $previous = $this->exception->previous();

        return $previous instanceof ValueException ? new self($this->settings, $previous) : null;
    }

    function globals() {
        $globals = $this->exception->globals();

        return $globals instanceof ValueGlobals ? new LimitedGlobals($this->settings, $globals) : null;
    }

    function locals() {
        $locals = $this->exception->locals();

        return is_array($locals) ? array_slice($locals, 0, $this->settings->maxLocalVariables) : null;
    }

    function stack() {
        $stack = $this->exception->stack();
        $stack = array_slice($stack, 0, $this->settings->maxStackFrames);

        $result = array();

        foreach ($stack as $stackFrame)
            $result[] = new LimitedStackFrame($this->settings, $stackFrame);

        return $result;
    }

    function className() { return $this->exception->className(); }

    function code() { return $this->exception->code(); }

    function message() { return $this->exception->message(); }

    function location() { return $this->exception->location(); }

    function numStackFrames() { return $this->exception->numStackFrames(); }

    function numLocals() { return $this->exception->numLocals(); }
}

class LimitedGlobals extends LimitedThing implements ValueGlobals {
    private $globals;

    function __construct(Limiter $settings, ValueGlobals $globals) {
        parent::__construct($settings);
        $this->globals = $globals;
    }

    function staticProperties() {
        $staticProperties = $this->globals->staticProperties();
        $staticProperties = array_slice($staticProperties, 0, $this->settings->maxStaticProperties);

        $result = array();

        foreach ($staticProperties as $property)
            $result[] = new LimitedObjectProperty($this->settings, $property);

        return $result;
    }

    function staticVariables() {
        $staticVariables = $this->globals->staticVariables();
        $staticVariables = array_slice($staticVariables, 0, $this->settings->maxStaticVariables);

        $result = array();

        foreach ($staticVariables as $variable)
            $result[] = new LimitedStaticVariable($this->settings, $variable);

        return $result;
    }

    function globalVariables() {
        $globalVariables = $this->globals->globalVariables();
        $globalVariables = array_slice($globalVariables, 0, $this->settings->maxGlobalVariables);

        $result = array();

        foreach ($globalVariables as $variable)
            $result[] = new LimitedVariable($this->settings, $variable);

        return $result;
    }

    function numStaticProperties() { return $this->globals->numStaticProperties(); }

    function numStaticVariables() { return $this->globals->numStaticVariables(); }

    function numGlobalVariables() { return $this->globals->numGlobalVariables(); }
}

class LimitedStackFrame extends LimitedThing implements ValueStackFrame {
    private $stackFrame;

    function __construct(Limiter $settings, ValueStackFrame $stackFrame) {
        parent::__construct($settings);
        $this->stackFrame = $stackFrame;
    }

    function arguments() {
        $args = $this->stackFrame->arguments();

        if (is_array($args)) {
            $args = array_slice($args, 0, $this->settings->maxFunctionArguments);

            $result = array();

            foreach ($args as $arg)
                $result[] = new LimitedValue($this->settings, $arg);

            return $result;
        } else {
            return null;
        }
    }

    function functionName() { return $this->stackFrame->functionName(); }

    function className() { return $this->stackFrame->className(); }

    function isStatic() { return $this->stackFrame->isStatic(); }

    function location() { return $this->stackFrame->location(); }

    function object() {
        $object = $this->stackFrame->object();

        return $object instanceof ValueObject ? new LimitedObject($this->settings, $object) : null;
    }

    function numArguments() { return $this->stackFrame->numArguments(); }
}

class LimitedVariable extends LimitedThing implements ValueVariable {
    private $variable;

    function __construct(Limiter $settings, ValueVariable $variable) {
        parent::__construct($settings);
        $this->variable = $variable;
    }

    function value() { return new LimitedValue($this->settings, $this->variable->value()); }

    function name() { return $this->variable->name(); }
}

class LimitedStaticVariable extends LimitedVariable implements ValueStaticVariable {
    private $variable;

    function __construct(Limiter $settings, ValueStaticVariable $variable) {
        parent::__construct($settings, $variable);
        $this->variable = $variable;
    }

    function functionName() { return $this->variable->functionName(); }

    function className() { return $this->variable->className(); }
}

class LimitedObjectProperty extends LimitedVariable implements ValueObjectProperty {
    private $property;

    function __construct(Limiter $settings, ValueObjectProperty $property) {
        parent::__construct($settings, $property);
        $this->property = $property;
    }

    function access() { return $this->property->access(); }

    function className() { return $this->property->className(); }

    function isDefault() { return $this->property->isDefault(); }
}
