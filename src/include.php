<?php

spl_autoload_register(function ($cls) {
    for ($cls = explode('\\', $cls); $cls; array_pop($cls)) {
        $sep = DIRECTORY_SEPARATOR;
        $php = __DIR__ . $sep . join($sep, $cls) . '.php';
        if (file_exists($php)) {
            /** @noinspection PhpIncludeInspection */
            require_once $php;
            break;
        }
    }
});

require_once __DIR__ . '/FailWhale/functions.php';
require_once __DIR__ . '/FailWhale/_Internal/functions.php';
