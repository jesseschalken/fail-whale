<?php

namespace ErrorHandler;

class AssertionFailedException extends \LogicException implements ExceptionHasFullTrace {
    private $expression, $fullStackTrace;

    /**
     * @param string $file
     * @param int    $line
     * @param string $expression
     * @param string $message
     * @param array  $fullStackTrace
     */
    function __construct($file, $line, $expression, $message, array $fullStackTrace) {
        parent::__construct($message);

        $this->file           = $file;
        $this->line           = $line;
        $this->expression     = $expression;
        $this->fullStackTrace = $fullStackTrace;
    }

    function getExpression() { return $this->expression; }

    function getFullTrace() { return $this->fullStackTrace; }
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

    function getFullTrace() { return $this->stackTrace; }

    function getLocalVariables() { return $this->localVariables; }
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

    function getFullTrace() { return $this->stackTrace; }
}
