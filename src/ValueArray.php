<?php

namespace ErrorHandler;

class ValueArray extends Value {
    static function introspect(Introspection $i, &$x) {
        foreach ($i->arrayCache as $entry)
            if ($entry->isSame($x))
                return $entry->result();

        $self = IntrospectionArrayCacheEntry::add($i, new self, $x);

        $self->isAssociative = array_is_associative($x);

        $index = 0;
        foreach ($x as $k => &$v) {
            $self->entries[$index] = new ValueArrayEntry;
            $self->entries[$index]->introspectImpl($i, $k, $v);
            $index++;
        }

        return $self;
    }

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
        $self = new self;
        $self->schema()->fromJSON($s, $x);

        return $self;
    }

    /** @var Value */
    private $key;
    /** @var Value */
    private $value;

    function key() { return $this->key; }

    function value() { return $this->value; }

    function introspectImpl(Introspection $i, $k, &$v) {
        $this->key   = Value::introspect($i, $k);
        $this->value = Value::introspect($i, $v);
    }

    function toJSON(JSONSerialize $s) { return $this->schema()->toJSON($s); }

    private function schema() {
        $schema = new JSONSchema;
        $schema->bindObject(0, $this->key, function ($j, $v) { return Value::fromJSON($j, $v); });
        $schema->bindObject(1, $this->value, function ($j, $v) { return Value::fromJSON($j, $v); });

        return $schema;
    }
}

