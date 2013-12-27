<?php

namespace ErrorHandler;

class ValueArray extends Value {
    /**
     * @param Introspection $i
     * @param array         $array
     * @param IntrospectionArrayCache    $cache
     *
     * @return ValueArray
     */
    static function introspectImpl(Introspection $i, array &$array, IntrospectionArrayCache $cache) {
        $self = new self;
        $cache->insert($array, $self);

        $self->isAssociative = array_is_associative($array);

        foreach ($array as $k => &$v)
            $self->entries[] = new ValueArrayEntry($i->introspect($k), $i->introspectRef($v));

        return $self;
    }

    private $isAssociative = false;
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

    function toJsonValueImpl(JsonSerialize $s) {
        return array('type' => 'array', 'array' => $s->addArray($this));
    }

    function serializeArray(JsonSerialize $s) {
        $result = array(
            'isAssociative' => $this->isAssociative,
            'entries'       => array(),
        );

        foreach ($this->entries as $entry)
            $result['entries'][] = array(
                'key'   => $s->toJsonValue($entry->key()),
                'value' => $s->toJsonValue($entry->value()),
            );

        return $result;
    }

    static function fromJsonValueImpl(JsonSerialize $pool, $index, array $v) {
        $self                = new self;
        $self->isAssociative = $v['isAssociative'];

        $pool->insertArray($index, $self);

        foreach ($v['entries'] as $entry)
            $self->entries[] = new ValueArrayEntry($pool->fromJsonValue($entry['key']),
                                              $pool->fromJsonValue($entry['value']));

        return $self;
    }
}

class ValueArrayEntry {
    private $key, $value;

    function __construct(Value $key, Value $value) {
        $this->key   = $key;
        $this->value = $value;
    }

    function key() { return $this->key; }

    function value() { return $this->value; }
}
