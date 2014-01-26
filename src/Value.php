<?php

namespace ErrorHandler\Value;

interface Visitor {
    function visitObject(Object1 $object);

    function visitArray(Array1 $array);

    function visitException(Exception $exception);

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

    function visitResource(Resource $r);

    /**
     * @param bool $bool
     *
     * @return mixed
     */
    function visitBool($bool);
}

interface Value {
    function acceptVisitor(Visitor $visitor);
}

interface Resource {
    /** @return string */
    function type();

    /** @return int */
    function id();
}

interface Object1 {
    /** @return string */
    function className();

    /** @return ObjectProperty[] */
    function properties();

    /** @return string */
    function hash();

    /** @return int */
    function id();
}

interface Exception {
    /** @return string */
    function className();

    /** @return string */
    function code();

    /** @return string */
    function message();

    /** @return self|null */
    function previous();

    /** @return CodeLocation */
    function location();

    /** @return Globals */
    function globals();

    /** @return Variable[]|null */
    function locals();

    /** @return StackFrame[] */
    function stack();
}

interface Globals {
    /** @return ObjectProperty[] */
    function staticProperties();

    /** @return StaticVariable[] */
    function staticVariables();

    /** @return Variable[] */
    function globalVariables();
}

interface CodeLocation {
    /** @return int */
    function line();

    /** @return string */
    function file();

    /** @return string[]|null */
    function sourceCode();
}

interface Variable {
    /** @return string */
    function name();

    /** @return Value */
    function value();
}

interface StaticVariable extends Variable {
    /** @return string */
    function functionName();

    /** @return string|null */
    function className();
}

interface ObjectProperty extends Variable {
    /** @return string */
    function access();

    /** @return string */
    function className();

    /** @return bool */
    function isDefault();
}

interface StackFrame {
    /** @return Value[]|null */
    function arguments();

    /** @return string */
    function functionName();

    /** @return string|null */
    function className();

    /** @return bool|null */
    function isStatic();

    /** @return CodeLocation|null */
    function location();

    /** @return Object1|null */
    function object();
}

interface Array1 {
    /** @return bool */
    function isAssociative();

    /** @return int */
    function id();

    /** @return ArrayEntry[] */
    function entries();
}

interface ArrayEntry {
    /** @return Value */
    function key();

    /** @return Value */
    function value();
}

