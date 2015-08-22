<?php

spl_autoload_register(function ($class) {
    $dir = __DIR__;
    foreach (explode('\\', $class) as $part) {
        $dir = $dir . DIRECTORY_SEPARATOR . $part;
        $php = $dir . '.php';
        if (file_exists($php))
            require_once $php;
        if (!file_exists($dir))
            break;
    }
});
