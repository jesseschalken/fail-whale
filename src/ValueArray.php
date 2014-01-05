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
        return new JsonSchemaObject(
            array(
                'type' => new JsonConst('array'),
                'id'   => new JsonArrayID($this),
            )
        );
    }

    function wholeSchema() {
        return new JsonSchemaObject(
            array(
                'isAssociative' => new JsonRef($this->isAssociative),
                'entries'       => new JsonRefObjectList($this->entries, function () { return new ValueArrayEntry; }),
            )
        );
    }
}

class JsonArrayID extends JsonSchema {
    private $a;

    function __construct(ValueArray $a) {
        $this->a = $a;
    }

    function toJSON(JsonSerializationState $s) {
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
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
     * @return JsonSchema
     */
    function schema() {
        return new JsonSchemaObject(
            array(
                'key'   => new JsonRefValue($this->key),
                'value' => new JsonRefValue($this->value),
            )
        );
    }
}

