php-error-handler
=================

A universal error handler and pretty printer for PHP

Use the error handler with:
```php
$e = new \ErrorHandler\ErrorHandler;
$e->bind();
```

Use the pretty printer with:
```php
$p = new \PrettyPrinter\PrettyPrinter;
print $p->prettyPrint( "This is a string." );
```
