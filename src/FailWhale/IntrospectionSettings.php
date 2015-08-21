<?php

namespace FailWhale;

final class IntrospectionSettings {
    public $maxArrayEntries      = INF;
    public $maxObjectProperties  = INF;
    public $maxStringLength      = INF;
    public $maxStackFrames       = INF;
    public $maxLocalVariables    = INF;
    public $maxStaticProperties  = INF;
    public $maxStaticVariables   = INF;
    public $maxGlobalVariables   = INF;
    public $maxFunctionArguments = INF;
    public $maxSourceCodeContext = 7;
    public $includeSourceCode    = true;
    /**
     * This prefix will be removed from the start of all file paths if present.
     *
     * @var string
     */
    public $fileNamePrefix = '';
    /**
     * This prefix will be removed from the start of all names of classes and functions.
     *
     * @var string
     */
    public $namespacePrefix = '\\';
}

