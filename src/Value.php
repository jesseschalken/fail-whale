<?php

namespace ErrorHandler;

interface ValueVisitor {
    function visitObject(ValueObject $object);

    function visitArray(ValueArray $array);

    function visitException(ValueException $exception);

    /**
     * @param string $string
     *
     * @return mixed
     */
    function visitString($string);

    /**
     * @param int $int
     *
     * @return mixed
     */
    function visitInt($int);

    function visitNull();

    function visitUnknown();

    /**
     * @param float $float
     *
     * @return mixed
     */
    function visitFloat($float);

    function visitResource(ValueResource $r);

    /**
     * @param bool $bool
     *
     * @return mixed
     */
    function visitBool($bool);
}

interface Value {
    function acceptVisitor(ValueVisitor $visitor);
}

interface ValueResource {
    /** @return string */
    function resourceType();

    /** @return int */
    function resourceID();
}

interface ValueObject {
    /** @return string */
    function className();

    /** @return ValueObjectProperty[] */
    function properties();

    /** @return string */
    function getHash();

    /** @return int */
    function id();
}

interface ValueException {
    /** @return string */
    function className();

    /** @return string */
    function code();

    /** @return string */
    function message();

    /** @return self|null */
    function previous();

    /** @return ValueExceptionCodeLocation */
    function location();

    /** @return ValueExceptionGlobalState */
    function globals();

    /** @return ValueVariable[]|null */
    function locals();

    /** @return ValueExceptionStackFrame[] */
    function stack();
}

interface ValueExceptionGlobalState {
    /** @return ValueObjectProperty[] */
    function getStaticProperties();

    /** @return ValueVariableStatic[] */
    function getStaticVariables();

    /** @return ValueVariable[] */
    function getGlobalVariables();
}

interface ValueExceptionCodeLocation {
    /** @return int */
    function line();

    /** @return string */
    function file();

    /** @return string[]|null */
    function sourceCode();
}

interface ValueVariable {
    /** @return string */
    function name();

    /** @return Value */
    function value();
}

interface ValueVariableStatic extends ValueVariable {
    /** @return string */
    function getFunction();

    /** @return string|null */
    function getClass();
}

interface ValueObjectProperty extends ValueVariable {
    /** @return string */
    function access();

    /** @return string */
    function className();

    /** @return bool */
    function isDefault();
}

interface ValueExceptionStackFrame {
    /** @return Value[]|null */
    function getArgs();

    /** @return string */
    function getFunction();

    /** @return string|null */
    function getClass();

    /** @return bool|null */
    function getIsStatic();

    /** @return ValueExceptionCodeLocation|null */
    function getLocation();

    /** @return ValueObject|null */
    function getObject();
}

interface ValueArray {
    /** @return bool */
    function isAssociative();

    /** @return int */
    function id();

    /** @return ValueArrayEntry[] */
    function entries();
}

interface ValueArrayEntry {
    /** @return Value */
    function key();

    /** @return Value */
    function value();
}

