<?php

namespace FailWhale;

class ErrorHandler {
    /**
     * @param callable $handleException
     * @param callable|null $handleIgnoredError
     */
    static function bind($handleException, $handleIgnoredError = null) {
        if (PHP_MAJOR_VERSION == 5)
            if (PHP_MINOR_VERSION == 3)
                $phpBug61767Fixed = PHP_RELEASE_VERSION >= 18;
            else if (PHP_MINOR_VERSION == 4)
                $phpBug61767Fixed = PHP_RELEASE_VERSION >= 8;
            else
                $phpBug61767Fixed = PHP_MINOR_VERSION > 4;
        else
            $phpBug61767Fixed = PHP_MAJOR_VERSION > 5;

        $lastError = error_get_last();

        set_error_handler(
            $handleError = function ($severity, $message, $file = null, $line = null, $context = null) use (
                &$lastError, $handleException, $phpBug61767Fixed, $handleIgnoredError
            ) {
                $lastError = error_get_last();

                if (error_reporting() & $severity) {
                    $error = new ErrorException($message, $severity, $file, $line, $context, 1);

                    if ($phpBug61767Fixed)
                        throw $error;
                    else if ($severity & (E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED))
                        throw $error;
                    else
                        call_user_func($handleException, $error);

                    exit;
                } else if (is_callable($handleIgnoredError)) {
                    $error = new ErrorException($message, $severity, $file, $line, $context, 1);

                    call_user_func($handleIgnoredError, $error);
                }

                return true;
            }
        );

        register_shutdown_function(
            function () use (&$lastError, $handleError, $handleException) {
                $error = error_get_last();

                if ($error === null || $error === $lastError)
                    return;

                $x = set_error_handler($handleError);
                restore_error_handler();

                if ($x !== $handleError)
                    return;

                ini_set('memory_limit', '-1');

                $type    = $error['type'];
                $message = $error['message'];
                $file    = $error['file'];
                $line    = $error['line'];
                $error   = new ErrorException($message, $type, $file, $line, null, 1);
                call_user_func($handleException, $error);
            }
        );

        set_exception_handler(
            function (\Exception $exception) use (&$lastError, $handleException) {
                // \DateTime->__construct() both throws an exception _and_ triggers a PHP error on invalid input.
                // The PHP error bypasses set_error_handler, so to avoid it being treated as a fatal error by the
                // shutdown handler, we have to set $lastError when handling the exception.
                $lastError = error_get_last();
                $handleException($exception);
            }
        );
    }
}

class ErrorException extends \ErrorException {
    private $context;

    /**
     * @param string $message
     * @param int $severity
     * @param string $file
     * @param int $line
     * @param array|null $context
     * @param int $traceSkip
     */
    function __construct($message, $severity, $file, $line, array $context = null, $traceSkip = 0) {
        parent::__construct($message, 0, $severity, $file, $line);

        $constants  = array(
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
        $this->code = isset($constants[$severity]) ? $constants[$severity] : 'E_?';

        $this->context = $context;

        set_exception_trace($this, array_slice(debug_backtrace(), 1 + $traceSkip));
    }

    function getContext() {
        return $this->context;
    }
}

/**
 * Same as \Exception except it includes a stack trace with $this for each stack frame
 *
 * @package ErrorHandler
 */
class Exception extends \Exception {
    function __construct($message = "", $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);

        $trace = debug_backtrace();

        for ($i = 0; isset($trace[$i]['object']) && $trace[$i]['object'] === $this; $i++) ;

        $trace = array_slice($trace, $i);

        set_exception_trace($this, $trace);
    }
}
