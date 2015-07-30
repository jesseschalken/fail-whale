<?php

namespace FailWhale;

final class Value {
    /**
     * Convert multiple values into HTML
     *
     * @param self[] $values
     * @return string
     */
    static function manyToHTML(array $values) {
        $html = '';
        $hasJS = false;
        foreach ($values as $value) {
            $html .= $value->toInlineHTML(!$hasJS);
            $hasJS = true;
        }

        ob_start();

        ?>
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <meta charset="utf-8">
                <title>fail whale PHP explorer</title>
            </head>
            <body><?= $html ?></body>
        </html>
        <?php

        return ob_get_clean();
    }

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
        $root = new Data\Root;
        $root->fromJSON($json);
        return new self($root);
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
        return self::manyToHTML(array($this));
    }

    /**
     * Converts the value into HTML suitable for concatenating/embedding in other HTML.
     *
     * @param bool $includeJS Whether to include the FailWhale javascript library. It is safe to include multiple
     *                        times, but the output will be bloated. You can also include web/script.js manually.
     * @return string
     */
    function toInlineHTML($includeJS = true) {
        ob_start();

        if ($includeJS) {
            ?>
            <script>
                <?= file_get_contents(__DIR__ . '/../web/script.js') ?>
            </script>
            <?php
        }

        $jsonHtml = htmlspecialchars($this->toJSON(), ENT_COMPAT, 'UTF-8');
        $uniqueId = 'fail-whale-' . mt_rand();

        ?>
        <pre id="<?= $uniqueId ?>"><?= $jsonHtml ?></pre>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                var json = document.getElementById('<?= $uniqueId ?>');
                var html = FailWhale.renderJSON(json.textContent, document);

                json.parentNode.replaceChild(html, json);
            });
        </script>
        <?php

        return ob_get_clean();
    }

    function toJSON($pretty = true) {
        return $this->root->toJSON($pretty);
    }

    function toString(PrettyPrinterSettings $settings = null) {
        $visitor = new PrettyPrinter($this->root, $settings);

        return $visitor->render()->toString();
    }
}

