<?php

namespace FailWhale;

/**
 * Same as \Exception except it includes a stack trace with $this for each
 * stack frame
 */
class ExceptionWithTraceObjects extends \Exception {
    public function __construct($message = "", $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);

        \FailWhale\set_exception_trace($this, debug_backtrace());
    }
}

