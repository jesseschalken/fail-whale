# fail-whale

`fail-whale` is a system for introspecting PHP values and exceptions and rendering them in plain text and interactive HTML.

## Installation

Install [the Composer package](https://packagist.org/packages/fail-whale/fail-whale)

## Usage

Example:

```php
use FailWhale\Value;

class Foo {
    private $baz = "bar";
    protected $an_int = 967;
    public $an_array = [
        'baz' => [
            8346 => 762.192,
            'key1' => "string with\nnew lines\n\t\tand\r\n\ttabs\nand a CR",
            'key2' => "random bytes \xc4\x08\x12\xb1",
            'key3' => M_PI,
            'key4' => null,
            'key6' => true,
        ]
    ];
    private $bar;

    function __construct() {
        $this->bar = new Bar();
        $baz =& $this->an_array['baz'];
        $baz['recurse'] =& $baz;
        $baz['stream'] = fopen('php://memory', 'wb');
    }
}

class Bar {
    private $copies;
    function __construct() {
        $this->copies = [$this, $this];
    }
}

print Value::introspect(new Foo)->toString();
```

```
new Foo {
    private $baz = "bar";
    protected $an_int = 967;
    public $an_array = array(
        "baz" => &array002 array(
            8346 => 762.192,
            "key1" => "string with
new lines
		and\r
	tabs
and a CR",
            "key2" => "random bytes \xc4\x08\x12\xb1",
            "key3" => 3.1415926535898,
            "key4" => null,
            "key6" => true,
            "recurse" => *array002,
            "stream" => stream,
        ),
    );
    private $bar = &object002 new Bar {
        private $copies = array(
            *object002 new Bar,
            *object002 new Bar,
        );
    };
}
```

A `Value` can represent an `Exception` or a single PHP value.

### `Value::introspect()`, `Value::introspectRef()`, `Value::introspectException()`

`Value::introspect()` and `Value::introspectRef()` will handle arbitrary PHP values, including recursive arrays (eg `$a = [&$a]`) and recursive objects.

`Value::introspectException()` will handle any `Exception` and retrieve:
- it's code, message, file and line
- a full stack trace, including function name, class name, arguments, `$this`, file and line
- the entire global state of the PHP program
    - global variables
    - static class properties
    - static variables
- if it is a `ErrorExceptionWithContext`, the local variables ("context") at the point that the PHP error occurred
- the source code which surrounds the line where the exception was thrown and the surrounding code for each function call on the stack

All `Value::introspect*()` methods optionally accept a `IntrospectionSettings` object.

### `Value::toJSON()`, `Value::fromJSON()`

`Value->toJSON()` will return a JSON string suitable for `Value::fromJSON()`.

### `Value::toHTML()`, `Value::toInlineHTML()`

`Value->toHTML()` will return a full HTML document which represents the value in a browsable, expandable/collapsible form.

`Value->toInlineHTML()` will return HTML suitable for embedding in another HTML document.

### `Value::toString()`

`Value->toString()` will pretty-print the value as a string. It optionally accepts a `PrettyPrinterSettings` object to control how the value is rendered.

PHP values (and exceptions) containing repeated arrays, objects and strings are handled gracefully, as are recursive arrays and objects.

Scalar values (`int`, `string`, `bool`, `float`, `null`) and non-recursive arrays are rendered as valid PHP code.

## Error Handler

### `ErrorUtil::setExceptionTrace()`, `ExceptionWithTraceObjects`

In order to see the `$this` object (current object) for each stack frame in an exception, you should overwrite the default trace for an exception with one provided by `debug_backtrace()` using `ErrorUtil::setExceptionTrace()`:

```php
use FailWhale\ErrorUtil;

$e = new Exception('oh no!');
ErrorUtil::setExceptionTrace($e, debug_backtrace());
throw $e;
```

It is more convenient to do this in the constructor of your exception:

```php
use FailWhale\ErrorUtil;

class BadException {
    function __construct($message) {
        parent::__construct($message);
        ErrorUtil::setExceptionTrace($this, debug_backtrace());
    }
}

throw new BadException('oh no!');
```

Or you could instantiate or extend `ExceptionWithTraceObjects` which will do this for you:

```php
use FailWhale\ExceptionWithTraceObjects;

throw new ExceptionWithTraceObjects('oh no!');
```

`ErrorUtil::setExceptionTrace()` uses reflection to set the private property `Exception::$trace`, which is returned by `$e->getTrace()`. Try not to think about that too much. ;)

You can also use `ErrorUtil::setExceptionTrace()` to remove the top stack frame from an exception, to avoid your error handler appearing in the trace, for example.

```php
use FailWhale\ErrorUtil;

set_error_handler(function ($type, $message, $file, $line, $context = null) {
    $e = new ErrorException($message, 0, $type, $file, $line);
    ErrorUtil::setExceptionTrace($e, array_slice($e->getTrace(), 1)); // <=
    throw $e;
})
```

### `ErrorExceptionWithContext`

In order to see the local variables for PHP errors, you should use `ErrorExceptionWithContext` in place of `ErrorException` in your error handler, and call `$e->setContext()` with the `$context` array provided to your error handler from `set_error_handler()`. For example:

```php
use FailWhale\ErrorExceptionWithContext;

set_error_handler(function ($type, $message, $file, $line, $context = null) {
    $e = new ErrorExceptionWithContext($message, 0, $type, $file, $line);
    $e->setContext($context); // <=
    throw $e;
})
```

### `ErrorUtil::phpErrorConstant()`, `ErrorUtil::phpErrorName()`

For a given PHP error type, `ErrorUtil::phpErrorConstant()` and `ErrorUtil::phpErrorName()` will return the name of the constant and descriptive name respectively.

```php
use FailWhale\ErrorUtil;

print ErrorUtil::phpErrorConstant(E_PARSE); // E_PARSE
print ErrorUtil::phpErrorName(E_PARSE); // Parse Error
```

This can be useful for setting the code (as opposed to the severity/level/type) for an `ErrorException`, which is usually set to _0_. Since `new ErrorException(...)` only accepts integers for `$code`, you should use `ErrorExceptionWithContext` instead and call `setCode()`. For example:

```php
use FailWhale\ErrorExceptionWithContext;
use FailWhale\ErrorUtil;

set_error_handler(function ($type, $message, $file, $line, $context = null) {
    $e = new ErrorException($message, 0, $type, $file, $line);
    $e->setCode(ErrorUtil::phpErrorConstant($type)); // <=
    throw $e;
})
```

### `ErrorUtil::setErrorAndExceptionHandler()`

`ErrorUtil::setErrorAndExceptionHandler()` provides a PHP error handler which does all of the above for you, in addition to handling fatal errors. You are welcome to use that:

```php
use FailWhale\ErrorUtil;

ErrorUtil::setErrorAndExceptionHandler(function (Exception $e) {
    if ($e instanceof ErrorException)
        print "A PHP error occurred!\n";
    else
        print "An exception occurred!\n";

    print $e->getMessage();
});
```

## Error Handler + Pretty Printer

Putting these two pieces together, an error handler which prints a interactive HTML version of an exception to the browser might look like this:

```php
use FailWhale\ErrorUtil;
use FailWhale\Value;

ErrorUtil::setErrorAndExceptionHandler(function (Exception $e) {
    $value = Value::introspectException($e);

    if (PHP_SAPI === 'cli')
        print $value->toString();
    else
        print $value->toHTML();
});
```
