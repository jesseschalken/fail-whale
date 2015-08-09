<?php

namespace FailWhale;

function array_get_exists(array $a, $k) {
    return array_key_exists($k, $a) ? $a[$k] : null;
}
