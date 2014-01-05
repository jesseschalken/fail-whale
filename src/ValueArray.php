<?php

namespace ErrorHandler;

class ValueArray extends Value {
    static function introspectImpl(Introspection $i, array &$x) {
        foreach ($i->arrayCache as $entry)
            if (ref_equal($entry->array, $x))
                return $entry->result;

        $entry         = new IntrospectionArrayCacheEntry;
        $entry->result = $self = new self;
        $entry->array  =& $x;

        $i->arrayCache[] = $entry;

        $self->isAssociative = array_is_associative($x);

        $index = 0;
        foreach ($x as $k => &$v) {
            $self->entries[$index] = new ValueArrayEntry;
            $self->entries[$index]->introspectImpl($i, $k, $v);
            $index++;
        }

        return $self;
    }

    private $isAssociative;
    /** @var ValueArrayEntry[] */
    private $entries = array();

    function isAssociative() { return $this->isAssociative; }

    function entries() { return $this->entries; }

    function subValues() {
        $x = parent::subValues();

        foreach ($this->entries as $kvPair) {
            $x[] = $kvPair->key();
            $x[] = $kvPair->value();
        }

        return $x;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderArray($this);
    }

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('isAssociative', $this->isAssociative);
        $schema->bindObjectList('entries', $this->entries, function ($j, $v) { return ValueArrayEntry::fromJSON($j, $v); });

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        $index =& $s->arrayIndexes[$this->id()];

        if ($index === null) {
            $index = count($s->root['arrays']);

            $s->root['arrays'][$index] = $this->schema()->toJSON($s);
        }

        return array('array', $index);
    }

    static function fromJSON(JsonDeSerializationState $s, $x) {
        $self =& $s->finishedArrays[$x[1]];
        if ($self === null) {
            $self = new self;
            $self->schema()->fromJSON($s, $s->root['arrays'][$x[1]]);
        }

        return $self;
    }
}

class ValueArrayEntry implements JsonSerializable {
    /** @var Value */
    private $key;
    /** @var Value */
    private $value;

    function key() { return $this->key; }

    function value() { return $this->value; }

    function introspectImpl(Introspection $i, $k, &$v) {
        $this->key   = $i->introspect($k);
        $this->value = $i->introspectRef($v);
    }

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindValue(0, $this->key);
        $schema->bindValue(1, $this->value);

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->schema()->toJSON($s);
    }

    static function fromJSON(JsonDeSerializationState $s, $x) {
        $self = new self;
        $self->schema()->fromJSON($s, $x);

        return $self;
    }
}

