<?php

namespace ErrorHandler;

function &ref_new($x = null) { return $x; }

function ref_get(&$x) { return $x; }

function ref_set(&$x, $y) { $x = $y; }

function array_get(array $a, $k) {
    return array_key_exists($k, $a) ? $a[$k] : null;
}

function array_set(array &$a, $k, $v) {
    if ($v !== null)
        $a[$k] = $v;
}
