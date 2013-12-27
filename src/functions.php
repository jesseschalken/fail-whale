<?php

namespace ErrorHandler;

function &ref_new($x = null) { return $x; }

function ref_get(&$x) { return $x; }

function ref_set(&$x, $y) { $x = $y; }

function ref_equal(&$x, &$y) {
    $xOld   = $x;
    $x      = new \stdClass;
    $result = $x === $y;
    $x      = $xOld;

    return $result;
}

function array_get(array $a, $k) {
    return array_key_exists($k, $a) ? $a[$k] : null;
}

function array_set(array &$a, $k, $v) {
    if ($v !== null)
        $a[$k] = $v;
}

function array_is_associative(array $a) {
    $i = 0;

    foreach ($a as $k => $v)
        if ($k !== $i++)
            return true;

    return false;
}
