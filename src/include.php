<?php

spl_autoload_register(function ($class) {
    $path = __DIR__;
    foreach (explode('\\', $class) as $part) {
        $path .= DIRECTORY_SEPARATOR . $part;
        if (file_exists("$path.php")) {
            require_once "$path.php";
        } else if (!file_exists($path)) {
            break;
        }
    }
});

