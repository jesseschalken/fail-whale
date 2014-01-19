<?php

namespace ErrorHandler;

class ValueArray extends Value {
    static function fromJSON(JSONUnserialize $s, $x) {
        $self =& $s->finishedArrays[$x[1]];
        if ($self === null) {
            $self = new self;
            $self->schema()->fromJSON($s, $s->root['arrays'][$x[1]]);
        }

        return $self;
    }

    private $isAssociative;
    /** @var ValueArrayEntry[] */
    private $entries = array();

    function isAssociative() { return $this->isAssociative; }

    function entries() { return $this->entries; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitArray($this); }

    function subValues() {
        $x = parent::subValues();

        foreach ($this->entries as $kvPair) {
            $x[] = $kvPair->key();
            $x[] = $kvPair->value();
        }

        return $x;
    }

    function toJSON(JSONSerialize $s) {
        $index =& $s->arrayIndexes[$this->id()];

        if ($index === null) {
            $index = count($s->root['arrays']);

            $json =& $s->root['arrays'][$index];
            $json = $this->schema()->toJSON($s);
        }

        return array('array', $index);
    }

    function setIsAssociative($isAssociative) {
        $this->isAssociative = $isAssociative;
    }

    function addEntry(Value $k, Value $v) {
        $this->entries[] = new ValueArrayEntry($k, $v);
    }

    private function schema() {
        $schema = new JSONSchema;
        $schema->bind('isAssociative', $this->isAssociative);
        $schema->bindObjectList('entries', $this->entries, function ($j, $v) {
            return ValueArrayEntry::fromJSON($j, $v);
        });

        return $schema;
    }
}

class ValueArrayEntry implements JSONSerializable {
    static function fromJSON(JSONUnserialize $s, $x) {
        return new self(Value::fromJSON($s, $x[0]), Value::fromJSON($s, $x[1]));
    }

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

    function toJSON(JSONSerialize $s) {
        return array($this->key->toJSON($s), $this->value->toJSON($s));
    }
}

