<?php

namespace ErrorHandler;

/**
 * Provides versions of json_encode and json_decode which work with arbitrary byte strings, not just valid UTF-8 ones.
 */
final class JSON {
    /**
     * @param mixed $value
     * @param bool  $pretty
     *
     * @return string
     */
    static function encode($value, $pretty = true) {
        $value = self::translateStrings($value, function ($x) { return utf8_encode($x); });
        $json  = json_encode($value, $pretty ? JSON_PRETTY_PRINT : 0);

        self::checkError();

        return $json;
    }

    /**
     * @param string $json
     *
     * @return mixed
     */
    static function decode($json) {
        $value = json_decode($json, true);

        self::checkError();

        $value = self::translateStrings($value, function ($x) { return utf8_decode($x); });

        return $value;
    }

    /**
     * @param mixed    $value
     * @param callable $f
     *
     * @throws \Exception
     * @return mixed
     */
    private static function translateStrings($value, \Closure $f) {
        if (is_string($value)) {
            return $f($value);
        } else if (is_float($value) ||
                   is_int($value) ||
                   is_null($value) ||
                   is_bool($value)
        ) {
            return $value;
        } else if (is_array($value)) {
            $result = array();

            foreach ($value as $k => $v) {
                $k = self::translateStrings($k, $f);
                $v = self::translateStrings($v, $f);

                $result[$k] = $v;
            }

            return $result;
        } else {
            throw new \Exception("Invalid JSON value");
        }
    }

    private static function checkError() {
        if (json_last_error() !== JSON_ERROR_NONE)
            throw new \Exception("JSON Error", json_last_error());
    }
}

