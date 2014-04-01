<?php

namespace ErrorHandler;

use ErrorHandler\JSON2\Root;

class Value {
    static function introspect($value) {
        $i = new JSON2\Introspection;

        return new self($i->root($i->introspect($value)));
    }

    static function introspectRef(&$value) {
        $i = new JSON2\Introspection;

        return new self($i->root($i->introspectRef($value)));
    }

    static function introspectException(\Exception $exception) {
        $i = new JSON2\Introspection;

        return new self($i->root($i->introspectException($exception)));
    }

    static function mockException() {
        $i = new JSON2\Introspection;
        return new self($i->root($i->mockException()));
    }

    static function fromJSON($json) {
        $root = new Root;
        $root->pushJson(JSON::decode($json));
        return new self($root);
    }

    private $root;

    private function __construct(JSON2\Root $root) {
        $this->root = $root;
    }

    function toJSON() {
        return JSON::encode($this->root);
    }

    function toHTML() {
        $js = <<<js
document.addEventListener('DOMContentLoaded', function () {
    var json = document.getElementById('the-json').textContent;
    var value = PrettyPrinter.renderJSON(json);
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

    function toString(PrettyPrinter $settings = null) {
        $visitor = new PrettyPrinterVisitor($settings ? : new PrettyPrinter, $this->root);

        return $visitor->renderRoot()->toString();
    }
}

interface ValueVisitor {
    function visitObject(ValueObject $object);

    function visitArray(ValueArray $array);

    function visitException(ValueException $exception);

    /**
     * @param ValueString $string
     *
     * @return mixed
     */
    function visitString(ValueString $string);

    /**
     * @param int $int
     *
     * @return mixed
     */
    function visitInt($int);

    function visitNull();

    function visitUnknown();

    /**
     * @param float $float
     *
     * @return mixed
     */
    function visitFloat($float);

    function visitResource(ValueResource $resource);

    /**
     * @param bool $bool
     *
     * @return mixed
     */
    function visitBool($bool);
}

interface ValueImpl {
    function acceptVisitor(ValueVisitor $visitor);
}

interface ValueResource {
    /**
     * @return string
     */
    function type();

    /**
     * @return int
     */
    function id();
}

interface ValueObject {
    /**
     * @return string
     */
    function className();

    /**
     * @return ValueObjectProperty[]
     */
    function properties();

    /**
     * @return string
     */
    function hash();

    /**
     * @return int
     */
    function id();

    /**
     * @return int
     */
    function propertiesMissing();
}

interface ValueException {
    /**
     * @return string
     */
    function className();

    /**
     * @return string
     */
    function code();

    /**
     * @return string
     */
    function message();

    /**
     * @return self|null
     */
    function previous();

    /**
     * @return ValueCodeLocation
     */
    function location();

    /**
     * @return ValueGlobals|null
     */
    function globals();

    /**
     * @return ValueVariable[]|null
     */
    function locals();

    /**
     * @return ValueStackFrame[]
     */
    function stack();

    /**
     * @return int
     */
    function stackMissing();

    /**
     * @return int
     */
    function localsMissing();
}

interface ValueGlobals {
    /**
     * @return ValueObjectProperty[]
     */
    function staticProperties();

    /**
     * @return ValueStaticVariable[]
     */
    function staticVariables();

    /**
     * @return ValueVariable[]
     */
    function globalVariables();

    /**
     * @return int
     */
    function staticPropertiesMissing();

    /**
     * @return int
     */
    function staticVariablesMissing();

    /**
     * @return int
     */
    function globalVariablesMissing();
}

interface ValueCodeLocation {
    /**
     * @return int
     */
    function line();

    /**
     * @return string
     */
    function file();

    /**
     * @return string[]|null
     */
    function sourceCode();
}

interface ValueVariable {
    /**
     * @return string
     */
    function name();

    /**
     * @return ValueImpl
     */
    function value();
}

interface ValueStaticVariable extends ValueVariable {
    /**
     * @return string
     */
    function functionName();

    /**
     * @return string|null
     */
    function className();
}

interface ValueObjectProperty extends ValueVariable {
    /**
     * @return string
     */
    function access();

    /**
     * @return string
     */
    function className();

    /**
     * @return bool
     */
    function isDefault();
}

interface ValueStackFrame {
    /**
     * @return ValueImpl[]|null
     */
    function arguments();

    /**
     * @return string
     */
    function functionName();

    /**
     * @return string|null
     */
    function className();

    /**
     * @return bool|null
     */
    function isStatic();

    /**
     * @return ValueCodeLocation|null
     */
    function location();

    /**
     * @return ValueObject|null
     */
    function object();

    /**
     * @return int
     */
    function argumentsMissing();
}

interface ValueArray {
    /**
     * @return bool
     */
    function isAssociative();

    /**
     * @return int
     */
    function id();

    /**
     * @return ValueArrayEntry[]
     */
    function entries();

    /**
     * @return int
     */
    function entriesMissing();
}

interface ValueArrayEntry {
    /**
     * @return ValueImpl
     */
    function key();

    /**
     * @return ValueImpl
     */
    function value();
}

interface ValueString {
    /**
     * @return int
     */
    function id();

    /**
     * @return string
     */
    function bytes();

    /**
     * @return int
     */
    function bytesMissing();
}

