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

