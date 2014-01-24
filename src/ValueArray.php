<?php

namespace ErrorHandler;

class ValueArray implements Value {
    private $isAssociative;
    /** @var ValueArrayEntry[] */
    private $entries = array();
    private $id;

    function isAssociative() { return $this->isAssociative; }

    function entries() { return $this->entries; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitArray($this); }

    function setIsAssociative($isAssociative) {
        $this->isAssociative = $isAssociative;
    }

    function addEntry(Value $k, Value $v) {
        $this->entries[] = new ValueArrayEntry($k, $v);
    }

    function setID($id) { $this->id = $id; }

    function id() { return $this->id; }
}

class ValueArrayEntry {
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

