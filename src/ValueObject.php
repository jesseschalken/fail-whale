<?php

namespace ErrorHandler;

class ValueObject extends Value {
    /**
     * @param Introspection $i
     * @param string        $hash
     * @param object        $object
     * @param IntrospectionObjectCache   $cache
     *
     * @return ValueObject
     */
    static function introspectImpl(Introspection $i, $hash, $object, IntrospectionObjectCache $cache) {
        $self = new self;
        $cache->insert($object, $hash, $self);

        $self->hash       = $hash;
        $self->className  = get_class($object);
        $self->properties = ValueVariable::introspectObjectProperties($i, $object);

        return $self;
    }

    function subValues() {
        $x = parent::subValues();

        foreach ($this->properties as $p)
            $x[] = $p->value();

        return $x;
    }

    private $hash;
    private $className;
    /** @var ValueVariable[] */
    private $properties = array();

    function className() { return $this->className; }

    function properties() { return $this->properties; }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderObject($this);
    }

    function toJsonValueImpl(JsonSerialize $s) {
        return array(
            'type'   => 'object',
            'object' => $s->addObject($this),
        );
    }

    function serializeObject(JsonSerialize $s) {
        $properties = array();

        foreach ($this->properties as $prop)
            $properties[] = $prop->toJsonValue($s);

        return array(
            'className'  => $this->className,
            'hash'       => $this->hash,
            'properties' => $properties,
        );
    }

    static function fromJsonValueImpl(JsonSerialize $pool, $index, array $v) {
        $self            = new self;
        $self->className = $v['className'];
        $self->hash      = $v['hash'];

        $pool->insertObject($index, $self);

        foreach ($v['properties'] as $prop)
            $self->properties[] = ValueVariable::fromJsonValue($pool, $prop);

        return $self;
    }
}
