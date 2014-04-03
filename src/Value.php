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

    static function introspectException(\Exception $exception, IntrospectionSettings $limits = null) {
        $i = new Introspection($limits);

        return new self($i->root($i->introspectException($exception)));
    }

    static function mockException() {
        $i = new Introspection;
        return new self($i->root($i->mockException()));
    }

    static function fromJSON($json) {
        $root = new Root;
        $root->pushJson(JSON::decode($json));
        return new self($root);
    }

    private $root;

    private function __construct(Root $root) {
        $this->root = $root;
    }

    function toHTML() {
        $js = <<<js
document.addEventListener('DOMContentLoaded', function () {
    var json = document.getElementById('the-json').textContent;
    var value = FailWhale.renderJSON(json);
    var body = document.getElementsByTagName('body')[0];

    body.innerHTML = '';
    body.appendChild(value);
});
js;

        $document           = new \DOMDocument;
        $document->encoding = 'UTF-8';
        $document->loadHTML('<!DOCTYPE html>');

        $script1 = $document->createElement('script');
        $script1->appendChild($document->createTextNode(file_get_contents(__DIR__ . '/../web/script.js')));

        $script2 = $document->createElement('script');
        $script2->appendChild($document->createTextNode($js));

        $pre = $document->createElement('pre');
        $pre->setAttribute('id', 'the-json');
        $pre->appendChild($document->createTextNode($this->toJSON()));

        $document->appendChild($script1);
        $document->appendChild($script2);
        $document->appendChild($pre);

        return $document->saveHTML();
    }

    function toJSON($pretty = true) {
        return JSON::encode($this->root->pullJson(), $pretty);
    }

    function toString(PrettyPrinterSettings $settings = null) {
        $visitor = new PrettyPrinter($this->root, $settings);

        return $visitor->render()->toString();
    }
}

