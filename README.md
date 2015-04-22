# fail-whale

A universal PHP error handler, pretty printer and PHP value browser.

## Installation

Install [the Composer package](https://packagist.org/packages/fail-whale/fail-whale)

## Usage
### Error Handler

`ErrorHandler::bind()` takes a `callable` which accepts an `Exception`, and calls it when the current PHP process fails for any reason - either an uncaught `Exception` or a PHP error, including fatal errors. No PHP failure will go undetected by `ErrorHandler::bind()`.

Example:

```php
use FailWhale\ErrorHandler;

ErrorHandler::bind(
    function (\Exception $e)
    {
        print "Oh noes!\n";
        print $e;
    }
);
```

`ErrorHandler::bind()` will:
- Bind an error handler using `set_error_handler()` which throws an `ErrorException` in the case of a PHP error.
- Ignore PHP errors according to the current `error_reporting` level (so `@bad_function()` works, for example), *except* if they are fatal.
- Set your exception handler using `set_exception_handler()`.
- Bind a shutdown handler using `register_shutdown_function()` to catch fatal PHP errors (which bypass the error handler set with `set_error_handler()`) and call your exception handler directly with the resulting `ErrorException`.
- Check for PHP Bug 61767 and call your exception handler directly with an `ErrorException` if it is not safe to throw it.

You can also handle errors ignored by the `error_reporting` setting (to log them, for example) by passing another `callable` as the second parameter to `ErrorHandler::bind()`. It will be called with the ignored `ErrorException` as its first parameter.

That's it. Easy.

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

### Error Handler + Pretty Printer

```php
use FailWhale\ErrorHandler;
use FailWhale\Value;

ErrorHandler::bind(
    function (\Exception $e)
    {
        $value = Value::introspectException($e);

        if (PHP_SAPI === 'cli')
            print $value->toString();
        else
            print $value->toHTML();
    }
);
```
