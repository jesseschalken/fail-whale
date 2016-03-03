<?php

namespace FailWhale;

use PureJSON\JSON;

final class Value {
    static function introspect($value, IntrospectionSettings $settings = null) {
        $i = new Introspection($settings);

        return new self($i->root($i->introspect($value)));
    }

    static function introspectRef(&$value, IntrospectionSettings $settings = null) {
        $i = new Introspection($settings);

        return new self($i->root($i->introspectRef($value)));
    }

    static function introspectException(\Exception $exception, IntrospectionSettings $limits = null) {
        $i = new Introspection($limits);

        return new self($i->root($i->introspectException($exception)));
    }

    static function mockException() {
        $i = new Introspection;
        return new self($i->root($i->mockException()));
    }

    static function fromJSON($json) {
        return new self(Data\Root::fromArray(JSON::decode($json, true)));
    }

    private $root;

    private function __construct(Data\Root $root) {
        $this->root = $root;
    }

    /**
     * Converts the value to a complete HTML document
     * @return string
     */
    function toHTML() {
        $html = $this->toInlineHTML(true);

        return <<<html
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>fail whale PHP explorer</title>
    </head>
    <body>$html</body>
</html>
html;
    }

    /**
     * Converts the value into HTML suitable for concatenating/embedding in other HTML.
     *
     * @param bool $includeJS Whether to include the FailWhale javascript library. It is safe to include multiple
     *                        times, but the output will be bloated. You can also include web/script.js manually.
     * @return string
     */
    function toInlineHTML($includeJS = true) {
        $html = '';

        if ($includeJS) {
            $html .= '<script>' . file_get_contents(__DIR__ . '/../web/script.js') . '</script>';
        }

        $html .= Util::callJS(<<<js
(function (data) {
    return FailWhale.render(data, document);
})
js
            , $this->root->toArray(), true);

        return $html;
    }

    function toJSON($pretty = true) {
        return JSON::encode($this->root->toArray(), true, $pretty);
    }

    function toString(PrettyPrinterSettings $settings = null) {
        $visitor = new PrettyPrinter($this->root, $settings);

        return $visitor->render();
    }
}

