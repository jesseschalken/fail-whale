<?php

namespace ErrorHandler;

class Value {
    static function introspect($value) {
        $introspection = new Introspection;

        return new self($introspection->introspect($value));
    }

    static function introspectRef(&$value) {
        $introspection = new Introspection;

        return new self($introspection->introspectRef($value));
    }

    static function introspectException(\Exception $exception) {
        $introspection = new Introspection;

        return new self($introspection->introspectException($exception));
    }

    static function mockException() {
        return new self(new MockException(new Introspection));
    }

    static function fromJSON($json) {
        $json = JSON::decode($json);

        return new self(new JSONValue($json, $json['root']));
    }

    private $impl;

    private function __construct(ValueImpl $impl) {
        $this->impl = $impl;
    }

    function toJSON() {
        $visitor      = new JSONSerialize;
        $root         = $this->impl->acceptVisitor($visitor);
        $json         = $visitor->result();
        $json['root'] = $root;

        return JSON::encode($json);
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
        if (!$settings instanceof PrettyPrinter)
            $settings = new PrettyPrinter;

        /** @var PrettyPrinterText $text */
        $text = $this->impl->acceptVisitor(new PrettyPrinterVisitor($settings));

        return $text->toString();
    }

    function limit(Limiter $settings) {
        return new self(new LimitedValue($settings, $this->impl));
    }
}

interface ValueVisitor {
    function visitObject(ValueObject $object);

    function visitArray(ValueArray $array);

    function visitException(ValueException $exception);

    /**
     * @param string $string
     *
     * @return mixed
     */
    function visitString($string);

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
    function numProperties();
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
    function numStackFrames();

    /**
     * @return int
     */
    function numLocals();
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
    function numStaticProperties();

    /**
     * @return int
     */
    function numStaticVariables();

    /**
     * @return int
     */
    function numGlobalVariables();
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
    function numArguments();
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
    function numEntries();
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

