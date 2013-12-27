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

        $this->localVariables = $localVariables;
        $this->stackTrace     = $stackTrace;
        $errorConstants       = array(
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
        $this->code           = isset($errorConstants[$severity]) ? $errorConstants[$severity] : 'E_?';
    }

    function getFullTrace() { return $this->stackTrace; }

    function getLocalVariables() { return $this->localVariables; }
}

/**
 * @param callable $handler
 */
function set_exception_handler($handler) {
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

    set_error_handler($errorHandler = function ($severity, $message, $file = null, $line = null,
                                                $localVars = null) use (&$lastError, $handler, $phpBug61767Fixed) {
        $lastError = error_get_last();

        if (error_reporting() & $severity) {
            $e = new ErrorException($severity, $message, $file, $line, $localVars,
                                    array_slice(debug_backtrace(), 1));

            if ($phpBug61767Fixed)
                throw $e;
            else if ($severity & (E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED))
                throw $e;
            else
                call_user_func($handler, $e);
        }

        return true;
    });

    register_shutdown_function(function () use (&$lastError, $errorHandler, $handler) {
        $e = error_get_last();

        if ($e === null || $e === $lastError)
            return;

        $x = set_error_handler($errorHandler);
        restore_error_handler();

        if ($x !== $errorHandler)
            return;

        ini_set('memory_limit', '-1');

        call_user_func($handler, new ErrorException(
            $e['type'],
            $e['message'],
            $e['file'],
            $e['line'],
            null,
            array_slice(debug_backtrace(), 1)
        ));
    });

    \set_exception_handler($handler);

    assert_options(ASSERT_CALLBACK, function ($file, $line, $expression, $message = 'Assertion failed') {
        throw new AssertionFailedException($file, $line, $expression, $message, array_slice(debug_backtrace(), 1));
    });
}

function simple_handler() {
    return function (\Exception $e) {
        $pp = PrettyPrinter::create();
        $pp->setMaxStringLength(100);
        $pp->setMaxArrayEntries(10);
        $pp->setMaxObjectProperties(10);

        output('error', $pp->prettyPrintException($e));
    };
}

/**
 * @param string $title
 * @param string $body
 */
function output($title, $body) {
    while (ob_get_level() > 0 && ob_end_clean()) ;

    if (PHP_SAPI === 'cli')
        fwrite(STDERR, $body);
    else {
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error', true, 500);
            header("Content-Type: text/html; charset=UTF-8", true);
        }

        print wrap_html($title, $body);
    }
}

/**
 * @param string $title
 * @param string $body
 *
 * @return string
 */
function wrap_html($title, $body) {
    $body  = htmlspecialchars($body, ENT_COMPAT, "UTF-8");
    $title = htmlspecialchars($title, ENT_COMPAT, "UTF-8");

    return <<<html
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>$title</title>
	</head>
	<body>
		<pre style="
			white-space: pre;
			font-family: 'DejaVu Sans Mono', 'Consolas', 'Menlo', monospace;
			font-size: 10pt;
			color: #000000;
			display: block;
			background: white;
			border: none;
			margin: 0;
			padding: 0;
			line-height: 16px;
			width: 100%;
		">$body</pre>
	</body>
</html>
html;
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

