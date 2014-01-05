<?php

namespace ErrorHandler;

class ValueArray extends Value {
    function introspectImpl(Introspection $i, &$x) {
        $entry =& $i->arrayCache[$this->id()];
        if ($entry !== null)
            return;
        $entry         = new IntrospectionArrayCacheEntry;
        $entry->result = $this;
        $entry->array  =& $x;

        $this->isAssociative = array_is_associative($x);

        $index = 0;
        foreach ($x as $k => &$v) {
            $this->entries[$index] = new ValueArrayEntry;
            $this->entries[$index]->introspectImpl($i, $k, $v);
            $index++;
        }
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

    function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('isAssociative', $this->isAssociative);
        $schema->bindObjectList('entries', $this->entries, function () { return new ValueArrayEntry; });

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        $index =& $s->arrayIDs[$this->id()];

        if ($index === null) {
            $index = count($s->root['arrays']);

            $s->root['arrays'][$index] = $this->schema()->toJSON($s);
        }

        return array('array', $index);
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $array =& $s->finishedArrays[$x[1]];
        if ($array !== $this) {
            $array = $this;
            $this->schema()->fromJSON($s, $s->root['arrays'][$x[1]]);
        }
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

    /**
     * @return JsonSerializable
     */
    function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindValue(0, $this->key);
        $schema->bindValue(1, $this->value);

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->schema()->toJSON($s);
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->schema()->fromJSON($s, $x);
    }
}

