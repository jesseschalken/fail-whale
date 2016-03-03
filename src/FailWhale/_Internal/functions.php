<?php

namespace FailWhale\_Internal;

use PureJSON\JSON;

function ref_eq(&$x, &$y) {
    $_ = $x;
    $x = new \stdClass;
    $r = $x === $y;
    $x = $_;
    return $r;
}

function ref_get(&$x) {
    return $x;
}

function array_is_assoc(array $a) {
    $i = 0;
    foreach ($a as $k => $v) {
        if ($k !== $i++) {
            return true;
        }
    }
    return false;
}

/**
 * Call a JavaScript function to render HTML
 * @param string $function A JavaScript expression that evaluates to a function
 * @param mixed  $param    A value which will be JSON-encoded and passed as the first parameter
 * @param bool   $binary
 * @return string
 */
function call_js($function, $param, $binary = false) {
    $id   = 'call-js-' . mt_rand();
    $json = JSON::encode($param, $binary);

    return <<<html
<span id="$id" style="display: none;"><script>
(function (callback, param) {
    var node = document.getElementById('$id');
    node.parentNode.replaceChild(callback(param), node);
})(($function), ($json));
</script></span>
html;
}


