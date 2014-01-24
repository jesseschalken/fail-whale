<?php

namespace ErrorHandler;

interface ValueArray {
    /** @return bool */
    function isAssociative();

    /** @return int */
    function id();

    /** @return ValueArrayEntry[] */
    function entries();
}

interface ValueArrayEntry {
    /** @return Value */
    function key();

    /** @return Value */
    function value();
}

class MutableValueArray implements Value, ValueArray {
    private $isAssociative;
    /** @var MutableValueArrayEntry[] */
    private $entries = array();
    private $id;

    function isAssociative() { return $this->isAssociative; }

    function entries() { return $this->entries; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitArray($this); }

    function setIsAssociative($isAssociative) {
        $this->isAssociative = $isAssociative;
    }

    function addEntry(Value $k, Value $v) {
        $this->entries[] = new MutableValueArrayEntry($k, $v);
    }

    function setID($id) { $this->id = $id; }

    function id() { return $this->id; }
}

class MutableValueArrayEntry implements ValueArrayEntry {
    /** @var Value */
    private $key;
    /** @var Value */
    private $value;

    function __construct(Value $key, Value $value) {
        $this->key   = $key;
        $this->value = $value;
    }

    function key() { return $this->key; }

    function value() { return $this->value; }
}

