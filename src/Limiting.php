<?php

namespace ErrorHandler;

class Limiter {
    public $maxArrayEntries = 1000;
    public $maxObjectProperties = 100;
    public $maxStringLength = 1000;
    public $maxStackFrames = 10;
    public $maxLocalVariables = 10;
    public $maxStaticProperties = 100;
    public $maxStaticVariables = 100;
    public $maxGlobalVariables = 100;
    public $maxFunctionArguments = 10;
    public $maxSourceCodeContext = 10;
}

