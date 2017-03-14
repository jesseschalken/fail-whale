<?php

namespace FailWhale;

class Value {
    static function introspect($value, IntrospectionSettings $settings = null) {
        $i = new Introspection($settings);

        return new self($i->root($i->introspect($value)));
    }

    static function introspectRef(&$value, IntrospectionSettings $settings = null) {
        $i = new Introspection($settings);

        return new self($i->root($i->introspectRef($value)));
    }

    /**
     * @param \Throwable $exception
     * @param IntrospectionSettings|null $limits
     * @return Value
     */
    static function introspectException($exception, IntrospectionSettings $limits = null) {
        $i = new Introspection($limits);

        return new self($i->root($i->introspectException($exception)));
    }

    static function mockException() {
        $i = new Introspection;
        return new self($i->root($i->mockException()));
    }

    static function fromJSON($json) {
        $root = new Root;
        $root->fromJSON($json);
        return new self($root);
    }

    private $root;

    private function __construct(Root $root) {
        $this->root = $root;
    }

    function toHTML() {
        $scriptJs = file_get_contents(__DIR__ . '/../web/script.js');
        $jsonHtml = htmlspecialchars($this->toJSON(), ENT_COMPAT, 'UTF-8');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <meta charset="utf-8">
                <title>fail whale PHP explorer</title>
                <script>
                    <?= $scriptJs ?>
                </script>
                <script type="text/javascript">
                    document.addEventListener('DOMContentLoaded', function () {
                        var json = document.getElementById('the-json');
                        var body = document.getElementsByTagName('body')[0];

                        body.innerHTML = '';
                        body.appendChild(FailWhale.renderJSON(json.textContent));
                    });
                </script>
            </head>
            <body>
                <pre id="the-json"><?= $jsonHtml ?></pre>
            </body>
        </html>
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

