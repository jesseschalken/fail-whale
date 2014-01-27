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
    /**
     * @return string
     */
    function type();

    /**
     * @return int
     */
    function id();
}

interface ValueObject {
    /**
     * @return string
     */
    function className();

    /**
     * @return ValueObjectProperty[]
     */
    function properties();

    /**
     * @return string
     */
    function hash();

    /**
     * @return int
     */
    function id();
}

interface ValueException {
    /**
     * @return string
     */
    function className();

    /**
     * @return string
     */
    function code();

    /**
     * @return string
     */
    function message();

    /**
     * @return self|null
     */
    function previous();

    /**
     * @return ValueCodeLocation
     */
    function location();

    /**
     * @return ValueGlobals|null
     */
    function globals();

    /**
     * @return ValueVariable[]|null
     */
    function locals();

    /**
     * @return ValueStackFrame[]
     */
    function stack();
}

interface ValueGlobals {
    /**
     * @return ValueObjectProperty[]
     */
    function staticProperties();

    /**
     * @return ValueStaticVariable[]
     */
    function staticVariables();

    /**
     * @return ValueVariable[]
     */
    function globalVariables();
}

interface ValueCodeLocation {
    /**
     * @return int
     */
    function line();

    /**
     * @return string
     */
    function file();

    /**
     * @return string[]|null
     */
    function sourceCode();
}

interface ValueVariable {
    /**
     * @return string
     */
    function name();

    /**
     * @return Value
     */
    function value();
}

interface ValueStaticVariable extends ValueVariable {
    /**
     * @return string
     */
    function functionName();

    /**
     * @return string|null
     */
    function className();
}

interface ValueObjectProperty extends ValueVariable {
    /**
     * @return string
     */
    function access();

    /**
     * @return string
     */
    function className();

    /**
     * @return bool
     */
    function isDefault();
}

interface ValueStackFrame {
    /**
     * @return Value[]|null
     */
    function arguments();

    /**
     * @return string
     */
    function functionName();

    /**
     * @return string|null
     */
    function className();

    /**
     * @return bool|null
     */
    function isStatic();

    /**
     * @return ValueCodeLocation|null
     */
    function location();

    /**
     * @return ValueObject|null
     */
    function object();
}

interface ValueArray {
    /**
     * @return bool
     */
    function isAssociative();

    /**
     * @return int
     */
    function id();

    /**
     * @return ValueArrayEntry[]
     */
    function entries();
}

interface ValueArrayEntry {
    /**
     * @return Value
     */
    function key();

    /**
     * @return Value
     */
    function value();
}

