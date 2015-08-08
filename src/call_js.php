<?php

namespace FailWhale;

use PureJSON\JSON;

/**
 * Call a JavaScript function to render HTML
 * @param string $function A JavaScript expression that evaluates to a function
 * @param mixed $param A value which will be JSON-encoded and passed as the first parameter
 * @param bool $binary
 * @return string
 */
function call_js($function, $param, $binary = false) {
    $id   = 'call-js-' . mt_rand();
    $json = JSON::encode($param, $binary, true);

    return <<<html
<script id="$id">
(function (callback, param) {
    document.addEventListener('DOMContentLoaded', function () {
        var node = document.getElementById('$id');
        node.parentNode.replaceChild(callback(param), node);
    });
})(($function), $json);
</script>
html;
}

