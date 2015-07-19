# fail-whale

A universal PHP error handler, pretty printer and PHP value browser.

## Installation

Install [the Composer package](https://packagist.org/packages/fail-whale/fail-whale)

## Usage

### Pretty Printer

`Value` provides a complete system for introspecting and rendering PHP values and exceptions, both as plain text and HTML. A `Value` can also be converted to JSON and back again.

Example:

```php
use FailWhale\Value;

class A {
    private $foo      = "bar";
    protected $an_int = 967;
    public $an_array  = array(
        'key'  => 'value',
        'key2' => 'value2',
        8      => 762.192,
    );
}

$a = new A;

print Value::introspect($a)->toString();
```

```
new A {
    private $foo = "bar";
    protected $an_int = 967;
    public $an_array = array(
        "key" => "value",
        "key2" => "value2",
        8 => 762.192,
    );
}
```

A `Value` can represent an `Exception` or a single PHP value.

The full list of methods for `Value` are:

- `Value::introspect()`
- `Value::introspectRef()`
- `Value::introspectException()`
- `Value::fromJSON()`
- `Value->toJSON()`
- `Value->toString()`
- `Value->toHTML()`

`Value::introspect()` (and `Value::introspectRef()`) will handle arbitrary PHP values, including recursive arrays (such as `$a = [&$a]`) and recursive objects.

`Value::introspectException()` will handle any `Exception` and retrieve:
- it's code, message, file and line
- a full stack trace, including function name, class name, arguments, `$this`, file and line
- the entire global state of the PHP program
    - global variables
    - static class properties
    - static variables
- if it is a `FailWhale\ErrorException`, the local variables at the point that the PHP error occurred
- the source code which surrounds the line where the exception was thrown and the surrounding code for each function call on the stack

All `Value::introspect*()` methods optionally accept a `IntrospectionSettings` object.

`Value->toJSON()` will return a JSON string suitable for `Value::fromJSON()`.

`Value->toHTML()` will return a full HTML document which represents the value in a browsable, expandable/collapsible form.

`Value->toString()` will return a string. It optionally accepts a `PrettyPrinterSettings` object to control how the value is rendered. PHP values (and exceptions) containing repeated arrays, objects and strings are handled gracefully, as are recursive arrays and objects.

### Error Handler

#### `\FailWhale\set_exception_trace()`, `\FailWhale\Exception`

In order to see the `$this` object (current object) for each stack frame in an exception, you should overwrite the default trace for an exception with one provided by `debug_backtrace()` using `set_exception_trace()`:

```php
$e = new \Exception('oh no!');
\FailWhale\set_exception_trace($e, debug_backtrace());
throw $e;
```

It is more convenient to do this in the constructor of your exception:

```php
class BadException {
    function __construct($message) {
        parent::__construct($message);
        \FailWhale\set_exception_trace($this, debug_backtrace());
    }
}

throw new BadException('oh no!');
```

Or you could instantiate or extend `FailWhale\Exception` which will do this for you:

```php
throw new \FailWhale\Exception('oh no!');
```

`\FailWhale\set_exception_trace()` uses reflection to set the private `$trace` property of `\Exception`, which is returned by `$e->getTrace()`. Try not to think about that too much. ;)

You can also use `\FailWhale\set_exception_trace()` to remove the top stack frame from an exception, to avoid your error handler appearing in the trace, for example.

```php
\set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext = null) {
    $e = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    \FailWhale\set_exception_trace($e, array_slice($e->getTrace(), 1)); // <=
    throw $e;
})
```

#### `\FailWhale\ErrorException`

In order to see the local variables for PHP errors, you should use `\FailWhale\ErrorException` in place of `\ErrorException` in your error handler, and call `$e->setContext()` with the `$errcontext` array provided to your error handler from `\set_error_handler()`. For example:

```php
\set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext = null) {
    $e = new \FailWhale\ErrorException($errstr, 0, $errno, $errfile, $errline);
    $e->setContext($errcontext); // <=
    throw $e;
})
```

#### `\FailWhale\php_error_constant()`, `\FailWhale\php_error_name()`

For a given PHP error type, `\FailWhale\php_error_constant()` and `\FailWhale\php_error_name()` will return the name of the constant and descriptive name respectively.

```php
print \FailWhale\php_error_constant(E_PARSE); // E_PARSE
print \FailWhale\php_error_name(E_PARSE); // Parse Error
```

This can be useful for setting the code (as opposed to the severity/level/type) for an `\ErrorException`, which is usually set to _0_. Since `new \ErrorException(...)` only accepts integers for `$code`, you should use `\FailWhale\ErrorException` instead and call `setCode()`. For example:

```php
\set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext = null) {
    $e = new \FailWhale\ErrorException($errstr, 0, $errno, $errfile, $errline);
    $e->setCode(\FailWhale\php_error_constant($errno)); // <=
    throw $e;
})
```

#### `\FailWhale\set_error_and_exception_handler()`

`\FailWhale\set_error_and_exception_handler()` provides a PHP error handler which does all of the above for you, in addition to handling fatal errors. You are welcome to use that:

```php
\FailWhale\set_error_and_exception_handler(function (\Exception $e) {
    if ($e instanceof \ErrorException)
        print "A PHP error occurred!\n";
    else
        print "An exception occurred!\n";

    print $e->getMessage();
});
```

#### Error Handler + Pretty Printer

Putting these two pieces together, an error handler which prints a browseable HTML version of an exception to the browser might look like this:

```php
\FailWhale\set_error_and_exception_handler(function (\Exception $e) {
    $value = \FailWhale\Value::introspectException($e);

    if (PHP_SAPI === 'cli')
        print $value->toString();
    else
        print $value->toHTML();
});
```
