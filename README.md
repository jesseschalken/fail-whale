## fail-whale

A universal PHP error handler, pretty printer and PHP value browser.

### Usage
#### Error Handler

`ErrorHandler::bind()` takes a `callable` which accepts an `Exception`, and calls it when the current PHP process fails for *any* reason, such as:

- an uncaught `Exception`
- an internal PHP error
- a call to `trigger_error()` (or `user_error()`)
- a *fatal* PHP error
- a failed `assert()`

No PHP failure will go undetected by `ErrorHandler::bind()`.

Example:

```php
use FailWhale\ErrorHandler;

ErrorHandler::bind(
    function (\Exception $e)
    {
        echo "There was an error!\n";
        echo $e;
    }
);
```

`ErrorHandler::bind()` will:
- Bind an error handler using `set_error_handler()` which throws an `ErrorException` in the case of a PHP error.
- Ignore PHP errors according to the current `error_reporting` level (so `@bad_function()` works, for example), *except if they are fatal*.
- Set your exception handler using `set_exception_handler()`.
- Bind an assertion callback using `assert_options(ASSERT_CALLBACK, ...)` which throws a `FailWhale\AssertionFailedException` when a PHP `assert()` fails.
- Bind a shutdown handler using `register_shutdown_function()` to catch fatal PHP errors (which bypass the error handler set with `set_error_handler()`) and call your exception handler directly with the resulting `ErrorException`.
- Check for PHP Bug 61767 and call your exception handler directly with an `ErrorException` if it is not safe to throw it.

That's it. Easy.

