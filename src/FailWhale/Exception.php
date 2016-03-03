<?php

namespace FailWhale;

/**
 * Same as \Exception except it includes a stack trace with $this for each
 * stack frame
 */
class Exception extends \Exception {
    public function __construct($message = "", $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);

        ErrorUtil::setExceptionTrace($this, debug_backtrace());
    }
}

