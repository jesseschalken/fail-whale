<?php

namespace ErrorHandler;

/**
 * Provides versions of json_encode and json_decode which work with arbitrary byte strings, not just valid UTF-8 ones.
 */
final class JSON {
    /**
     * @param mixed  $value
     * @param string $nl
     *
     * @return string
     */
    static function encode($value, $nl = "\n") {
        if (is_object($value)) {
            $value2 = array();
            foreach (get_object_vars($value) as $key => $value)
                if ($value !== null)
                    $value2[$key] = $value;
            $value = $value2;
        }

        if (is_array($value)) {
            if (empty($value))
                return '[]';

            $nl2   = "$nl    ";
            $lines = array();

            if (self::isAssoc($value)) {
                $start = "{";
                $end   = "}";

                foreach ($value as $k => $v)
                    $lines[] = self::encode("$k", $nl2) . ": " . self::encode($v, $nl2);
            } else {
                $start = "[";
                $end   = "]";

                foreach ($value as $v)
                    $lines[] = self::encode($v, $nl2);
            }

            return $start . $nl2 . join(",$nl2", $lines) . $nl . $end;
        } else {
            $value = self::translateStrings($value, function ($x) { return utf8_encode($x); });
            $json  = json_encode($value);

            self::checkError();

            return $json;
        }
    }

    static function isAssoc(array $array) {
        $i = 0;
        foreach ($array as $k => $v)
            if ($k !== $i++)
                return true;

        return false;
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
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON Error", json_last_error());
        }
    }
}

