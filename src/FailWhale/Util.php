<?php

namespace FailWhale;

use PureJSON\JSON;

final class Util {
    /**
     * Call a JavaScript function to render HTML
     * @param string $function A JavaScript expression that evaluates to a function
     * @param mixed $param A value which will be JSON-encoded and passed as the first parameter
     * @param bool $binary
     * @return string
     */
    static function callJS($function, $param, $binary = false) {
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

    /**
     * @param array $a
     * @return bool
     */
    static function isAssoc(array $a) {
        $i = 0;
        foreach ($a as $k => $v)
            if ($k !== $i++)
                return true;
        return false;
    }

    static function refGet(&$x) {
        return $x;
    }

    static function refEq(&$x, &$y) {
        $_ = $x;
        $x = new \stdClass;
        $r = $x === $y;
        $x = $_;
        return $r;
    }
}

