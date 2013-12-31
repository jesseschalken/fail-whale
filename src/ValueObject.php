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
        $self->properties = ValueObjectProperty::introspectObjectProperties($i, $object);

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
    /** @var ValueObjectProperty[] */
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
            $self->properties[] = ValueObjectProperty::fromJsonValue($pool, $prop);

        return $self;
    }
}

class ValueObjectProperty extends ValueVariable {
    static function fromJsonValue(JsonSerialize $pool, $prop) {
        $self            = new self($prop['name'], $pool->fromJsonValue($prop['value']));
        $self->access    = $prop['access'];
        $self->isDefault = $prop['isDefault'];
        $self->className = $prop['className'];

        return $self;
    }

    /**
     * @param Introspection $i
     * @param object        $object
     *
     * @return self[]
     */
    static function introspectObjectProperties(Introspection $i, $object) {
        $properties = array();

        for ($reflection = new \ReflectionObject($object);
             $reflection !== false;
             $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || $property->class !== $reflection->name)
                    continue;

                $property->setAccessible(true);

                $self            = new self($property->name, $i->introspect($property->getValue($object)));
                $self->className = $property->class;
                $self->access    = $i->propertyOrMethodAccess($property);
                $self->isDefault = $property->isDefault();

                $properties[] = $self;
            }
        }

        return $properties;
    }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("$this->access ");
    }

    private $className;
    private $access;
    private $isDefault;

    function toJsonValue(JsonSerialize $s) {
        return array(
            'name'      => $this->name(),
            'value'     => $s->toJsonValue($this->value()),
            'className' => $this->className,
            'access'    => $this->access,
            'isDefault' => $this->isDefault,
        );
    }
}
