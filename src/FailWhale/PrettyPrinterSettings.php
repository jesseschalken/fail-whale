<?php

namespace FailWhale;

final class PrettyPrinterSettings {
    public $escapeTabsInStrings            = false;
    public $escapeNewlineInStrings         = false;
    public $showExceptionGlobalVariables   = true;
    public $showExceptionLocalVariables    = true;
    public $showExceptionStackTrace        = true;
    public $showExceptionSourceCode        = true;
    public $showExceptionFunctionArguments = true;
    public $showExceptionFunctionObject    = true;
    public $showObjectProperties           = true;
    public $showArrayEntries               = true;
    public $showStringContents             = true;
    public $longStringThreshold            = INF;
    public $maxStringLength                = INF;
    public $useShortArraySyntax            = false;
    public $indentStackTraceFunctions      = true;
}

