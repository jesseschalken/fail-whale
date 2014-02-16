<?php

namespace ErrorHandler;

class ValueFilter implements ValueImpl {
    private $value;

    function __construct(ValueImpl $value) { $this->value = $value; }

    function acceptVisitor(ValueVisitor $visitor) { return $this->value->acceptVisitor($visitor); }
}

class ValueVisitorFilter implements ValueVisitor {
    private $visitor;

    function __construct(ValueVisitor $visitor) { $this->visitor = $visitor; }

    function visitObject(ValueObject $object) { return $this->visitor->visitObject($object); }

    function visitArray(ValueArray $array) { return $this->visitor->visitArray($array); }

    function visitException(ValueException $exception) { return $this->visitor->visitException($exception); }

    function visitString($string) { return $this->visitor->visitString($string); }

    function visitInt($int) { return $this->visitor->visitInt($int); }

    function visitNull() { return $this->visitor->visitNull(); }

    function visitUnknown() { return $this->visitor->visitUnknown(); }

    function visitFloat($float) { return $this->visitor->visitFloat($float); }

    function visitResource(ValueResource $resource) { return $this->visitor->visitResource($resource); }

    function visitBool($bool) { return $this->visitor->visitBool($bool); }
}

class ValueObjectFilter implements ValueObject {
    private $object;

    function __construct(ValueObject $object) { $this->object = $object; }

    function className() { return $this->object->className(); }

    function properties() { return $this->object->properties(); }

    function hash() { return $this->object->hash(); }

    function id() { return $this->object->id(); }

    function numProperties() { return $this->object->numProperties(); }
}

class ValueArrayFilter implements ValueArray {
    private $array;

    function __construct(ValueArray $array) { $this->array = $array; }

    function isAssociative() { return $this->array->isAssociative(); }

    function id() { return $this->array->id(); }

    function entries() { return $this->array->entries(); }

    function numEntries() { return $this->array->numEntries(); }
}

class ValueArrayEntryFilter implements ValueArrayEntry {
    private $entry;

    function __construct(ValueArrayEntry $entry) { $this->entry = $entry; }

    function key() { return $this->entry->key(); }

    function value() { return $this->entry->value(); }
}

class ValueVariableFilter implements ValueVariable {
    private $variable;

    function __construct(ValueVariable $variable) { $this->variable = $variable; }

    function name() { return $this->variable->name(); }

    function value() { return $this->variable->value(); }
}

class ValueObjectPropertyFilter extends ValueVariableFilter implements ValueObjectProperty {
    private $property;

    function __construct(ValueObjectProperty $property) {
        parent::__construct($property);
        $this->property = $property;
    }

    function access() { return $this->property->access(); }

    function className() { return $this->property->className(); }

    function isDefault() { return $this->property->isDefault(); }
}

class ValueExceptionFilter implements ValueException {
    private $exception;

    function __construct(ValueException $exception) { $this->exception = $exception; }

    function className() { return $this->exception->className(); }

    function code() { return $this->exception->code(); }

    function message() { return $this->exception->message(); }

    function previous() { return $this->exception->previous(); }

    function location() { return $this->exception->location(); }

    function globals() { return $this->exception->globals(); }

    function locals() { return $this->exception->locals(); }

    function stack() { return $this->exception->stack(); }

    function numStackFrames() { return $this->exception->numStackFrames(); }

    function numLocals() { return $this->exception->numLocals(); }
}

class ValueStackFrameFilter implements ValueStackFrame {
    private $stackFrame;

    function __construct(ValueStackFrame $stackFrame) { $this->stackFrame = $stackFrame; }

    function arguments() { return $this->stackFrame->arguments(); }

    function functionName() { return $this->stackFrame->functionName(); }

    function className() { return $this->stackFrame->className(); }

    function isStatic() { return $this->stackFrame->isStatic(); }

    function location() { return $this->stackFrame->location(); }

    function object() { return $this->stackFrame->object(); }

    function numArguments() { return $this->stackFrame->numArguments(); }
}

class ValueGlobalsFilter implements ValueGlobals {
    private $globals;

    function __construct(ValueGlobals $globals) { $this->globals = $globals; }

    function staticProperties() { return $this->globals->staticProperties(); }

    function staticVariables() { return $this->globals->staticVariables(); }

    function globalVariables() { return $this->globals->globalVariables(); }

    function numStaticProperties() { return $this->globals->numStaticProperties(); }

    function numStaticVariables() { return $this->globals->numStaticVariables(); }

    function numGlobalVariables() { return $this->globals->numGlobalVariables(); }
}

class ValueResourceFilter implements ValueResource {
    private $resource;

    function __construct(ValueResource $resource) { $this->resource = $resource; }

    function type() { return $this->resource->type(); }

    function id() { return $this->resource->id(); }
}

class ValueCodeLocationFilter implements ValueCodeLocation {
    private $location;

    function __construct(ValueCodeLocation $location) { $this->location = $location; }

    function line() { return $this->location->line(); }

    function file() { return $this->location->file(); }

    function sourceCode() { return $this->location->sourceCode(); }
}

class ValueStaticVariableFilter extends ValueVariableFilter implements ValueStaticVariable {
    private $variable;

    function __construct(ValueStaticVariable $variable) {
        $this->variable = $variable;
        parent::__construct($variable);
    }

    function functionName() { return $this->variable->functionName(); }

    function className() { return $this->variable->className(); }
}
