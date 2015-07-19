<?php

/**
 * Some classes and functions to assist with error handling.
 */

namespace FailWhale;

/**
 * Sets the stack trace returned by `$e->getTrace()` to the specified value.
 *
 * Stack frames for any method calls on the exception itself are excluded.
 *
 * Use this (passing `debug_backtrace()` as the value) so your exceptions have
 * `$this` for each stack frame.
 *
 * @param \Exception $e
 * @param array $trace
 */
function set_exception_trace(\Exception $e, array $trace) {
    $i = 0;
    while (
        isset($trace[$i]['object']) &&
        $trace[$i]['object'] === $e
    ) {
        $i++;
    }

    $trace = array_slice($trace, $i);

    $prop = new \ReflectionProperty('Exception', 'trace');
    $prop->setAccessible(true);
    $prop->setValue($e, $trace);
}

/**
 * @param int $type
 * @return string
 */
function php_error_constant($type) {
    /** @var string[] $values */
    static $values = array(
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    );

    return array_key_exists($type, $values) ? $values[$type] : 'E_?';
}

/**
 * @param int $type
 * @return string
 */
function php_error_name($type) {
    /** @var string[] $values */
    static $values = array(
        E_ERROR             => 'Fatal Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Strict Standards',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated',
    );

    return array_key_exists($type, $values) ? $values[$type] : 'Unknown Error';
}

/**
 * Covers PHP errors, fatal PHP errors and uncaught exceptions.
 * - Binds the callback as the uncaught exception handler.
 * - Binds an error handler that throws `ErrorException` exceptions.
 * - Binds a shutdown function which catches fatal PHP errors.
 * @param callable $handler Will be called with the uncaught \Exception or \ErrorException.
 */
function set_error_and_exception_handler(callable $handler) {
    set_exception_handler($handler);

    set_error_handler(function ($type, $message, $file, $line, $context = null) {
        $e = new ErrorException($message, 0, $type, $file, $line);
        $e->setCode(php_error_constant($type));
        $e->setContext($context);
        // remove the top stack frame (this function)
        set_exception_trace($e, array_slice($e->getTrace(), 1));
        throw $e;
    });

    register_shutdown_function(function () use ($handler) {
        $e = error_get_last();
        if ($e !== null && ($e['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
            $e = new ErrorException(
                $e['message'],
                0,
                $e['type'],
                $e['file'],
                $e['line']
            );
            $e->setCode(php_error_constant($e['type']));
            set_exception_trace($e, array());
            $handler($e);
        }
    });
}

/**
 * Same as \ErrorException except includes a stack trace with $this for each stack frame, and provides a method to set
 * the context (local variables) provided by PHP's error handler.
 */
class ErrorException extends \ErrorException {
    /** @var array|null */
    private $context;

    function __construct(
        $message = "",
        $code = 0,
        $severity = 1,
        $filename = null,
        $lineno = null,
        \Exception $previous = null
    ) {
        parent::__construct($message, $code, $severity, $filename, $lineno, $previous);

        set_exception_trace($this, debug_backtrace());
    }

    /**
     * Since `ErrorHandler::__construct()` only accepts integers for the `$code`, you can use this to set it to
     * something besides an integer.
     * @param mixed|int $code
     */
    function setCode($code) {
        $this->code = $code;
    }

    /**
     * Set the context (local variables)
     * @param array|null $context
     */
    function setContext(array $context = null) {
        $this->context = $context;
    }

    /**
     * Get the context (local variables)
     * @return array|null
     */
    function getContext() {
        return $this->context;
    }
}

/**
 * Same as \Exception except it includes a stack trace with $this for each stack frame
 */
class Exception extends \Exception {
    function __construct($message = "", $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);

        set_exception_trace($this, debug_backtrace());
    }
}
