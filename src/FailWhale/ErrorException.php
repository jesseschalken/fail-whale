<?php

namespace FailWhale;

/**
 * Same as \ErrorException except includes a stack trace with $this for each
 * stack frame, and provides a method to set the context (local variables)
 * provided by PHP's error handler.
 */
class ErrorException extends \ErrorException {
    /** @var array|null */
    private $context;

    public function __construct(
        $message = "",
        $code = 0,
        $severity = 1,
        $filename = null,
        $lineno = null,
        \Exception $previous = null
    ) {
        parent::__construct($message, $code, $severity, $filename, $lineno, $previous);

        ErrorUtil::setExceptionTrace($this, debug_backtrace());
    }

    /**
     * Since `ErrorHandler::__construct()` only accepts integers for the
     * `$code`, you can use this to set it to something besides an integer.
     * @param mixed|int $code
     */
    public function setCode($code) {
        $this->code = $code;
    }

    /**
     * Set the context (local variables)
     * @param array|null $context
     */
    public function setContext(array $context = null) {
        $this->context = $context;
    }

    /**
     * Get the context (local variables)
     * @return array|null
     */
    public function getContext() {
        return $this->context;
    }
}

