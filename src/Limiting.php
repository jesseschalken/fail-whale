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

