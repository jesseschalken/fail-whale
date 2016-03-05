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

interface JsonSerializable {
    /** @return string */
    public static function jsonType();
}

/**
 * @param mixed $value
 * @return mixed
 * @throws \Exception
 */
function json_serialize($value) {
    if (is_array($value)) {
        if (array_is_assoc($value)) {
            throw new \Exception('Associative arrays are not supported');
        } else {
            $result = array();
            foreach ($value as $v) {
                $result[] = json_serialize($v);
            }
            return $result;
        }
    } else if ($value instanceof JsonSerializable) {
        $result = array('@type' => $value->jsonType());
        foreach (get_object_vars($value) as $k => $v) {
            $result[$k] = json_serialize($v);
        }
        return $result;
    } else {
        return $value;
    }
}

/**
 * @param mixed    $value
 * @param string[] $classes
 * @return mixed
 * @throws \Exception
 */
function json_deserialize($value, array $classes) {
    if (is_array($value)) {
        if (array_is_assoc($value)) {
            $type = $value['@type'];
            /** @var mixed $class */
            foreach ($classes as $class) {
                if ($class::jsonType() === $type) {
                    $object = new $class();
                    foreach (get_object_vars($object) as $k => $v) {
                        $object->$k = json_deserialize(isset($value[$k]) ? $value[$k] : null, $classes);
                    }
                    return $object;
                }
            }
            throw new \Exception("Unknown type '$type'");
        } else {
            $result = array();
            foreach ($value as $v) {
                $result[] = json_deserialize($v, $classes);
            }
            return $result;
        }
    } else {
        return $value;
    }
}

