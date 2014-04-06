<?php

namespace FailWhale;

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

        $handleError = function ($severity, $message, $file = null, $line = null,
                                 $localVars = null) use (
            &$lastError, $handleException,
            $phpBug61767Fixed, $handleIgnoredError
        ) {
            $lastError    = error_get_last();
            $ignore       = !(error_reporting() & $severity);
            $handleIgnore = is_callable($handleIgnoredError);
            if (!$ignore || $handleIgnore) {
                $e = new ErrorException($severity, $message, $file, $line, $localVars,
                                        array_slice(debug_backtrace(), 1));

                if (!$ignore)
                    if ($phpBug61767Fixed)
                        throw $e;
                    else if ($severity & (E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED))
                        throw $e;
                    else
                        call_user_func($handleException, $e);
                else if ($handleIgnore)
                    call_user_func($handleIgnoredError, $e);
            }

            return true;
        };

        $handleShutdown = function () use (&$lastError, $handleError, $handleException) {
            $e = error_get_last();

            if ($e === null || $e === $lastError)
                return;

            $x = set_error_handler($handleError);
            restore_error_handler();

            if ($x !== $handleError)
                return;

            ini_set('memory_limit', '-1');

            call_user_func($handleException, new ErrorException(
                $e['type'],
                $e['message'],
                $e['file'],
                $e['line'],
                null,
                array_slice(debug_backtrace(), 1)
            ));
        };

        $handleAssert = function ($file, $line, $expression, $message = 'Assertion failed') {
            throw new AssertionFailedException($file, $line, $expression, $message, array_slice(debug_backtrace(), 1));
        };

        set_error_handler($handleError);
        register_shutdown_function($handleShutdown);
        set_exception_handler($handleException);
        assert_options(ASSERT_CALLBACK, $handleAssert);
    }
}

class AssertionFailedException extends \LogicException implements ExceptionHasFullTrace {
    private $expression, $fullStackTrace;

    /**
     * @param string $file
     * @param int $line
     * @param string $expression
     * @param string $message
     * @param array $fullStackTrace
     */
    function __construct($file, $line, $expression, $message, array $fullStackTrace) {
        parent::__construct($message);

        $this->file           = $file;
        $this->line           = $line;
        $this->expression     = $expression;
        $this->fullStackTrace = $fullStackTrace;
    }

    function getExpression() {
        return $this->expression;
    }

    function getFullTrace() {
        return $this->fullStackTrace;
    }
}

class ErrorException extends \ErrorException implements ExceptionHasFullTrace, ExceptionHasLocalVariables {
    private $localVariables, $stackTrace;

    function __construct($severity, $message, $file, $line, array $localVariables = null, array $stackTrace) {
        parent::__construct($message, 0, $severity, $file, $line);

        $constants = array(
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

        $this->localVariables = $localVariables;
        $this->stackTrace     = $stackTrace;
        $this->code           = isset($constants[$severity]) ? $constants[$severity] : 'E_?';
    }

    function getFullTrace() {
        return $this->stackTrace;
    }

    function getLocalVariables() {
        return $this->localVariables;
    }
}

/**
 * Same as \Exception except it includes a full stack trace
 *
 * @package ErrorHandler
 */
class Exception extends \Exception implements ExceptionHasFullTrace {
    private $stackTrace;

    function __construct($message = "", $code = 0, \Exception $previous = null) {
        $trace = debug_backtrace();

        for ($i = 0; isset($trace[$i]['object']) && $trace[$i]['object'] === $this; $i++) ;

        $this->stackTrace = array_slice($trace, $i);

        parent::__construct($message, $code, $previous);
    }

    function getFullTrace() {
        return $this->stackTrace;
    }
}
