<?php

namespace ErrorHandler;

class ValueObject extends Value {
    static function introspectImpl(Introspection $i, &$x) {
        $hash = spl_object_hash($x);

        $self =& $i->objectCache[$hash];
        if ($self !== null)
            return $self;
        $self = new self;

        $i->objects[] = $x;

        $self->hash       = $hash;
        $self->className  = get_class($x);
        $self->properties = ValueObjectProperty::introspectObjectProperties($i, $x);

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

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('className', $this->className);
        $schema->bindRef('hash', $this->hash);

        $schema->bindObjectList('properties', $this->properties, function ($j, $v) {
            return ValueObjectProperty::fromJSON($j, $v);
        });

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        $id =& $s->objectIndexes[$this->id()];

        if ($id === null) {
            $id = count($s->root['objects']);

            $s->root['objects'][$id] = $this->schema()->toJSON($s);
        }

        return array('object', $id);
    }

    static function fromJSON(JsonDeSerializationState $s, $x) {
        if ($x === null)
            return null;

        $self =& $s->finishedObjects[$x[1]];

        if ($self === null) {
            $self = new self;
            $self->schema()->fromJSON($s, $s->root['objects'][$x[1]]);
        }

        return $self;
    }
}

class ValueObjectProperty extends ValueVariable {
    static function mockStatic(Introspection $i) {
        $self            = static::introspect($i, 'blahProperty', ref_new());
        $self->className = 'BlahClass';
        $self->access    = 'private';
        $self->isDefault = false;
        $globals[]       = $self;

        return $globals;
    }

    static protected function create() { return new self; }

    protected static function introspectObjectProperty(Introspection $i, $name, &$value, \ReflectionProperty $p) {
        $self            = static::introspect($i, $name, $value);
        $self->className = $p->class;
        $self->access    = $i->propertyOrMethodAccess($p);
        $self->isDefault = $p->isDefault();

        return $self;
    }

    private $className;
    private $access;
    private $isDefault;

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

                $properties[] = self::introspectObjectProperty($i, $property->name,
                                                               ref_new($property->getValue($object)), $property);
            }
        }

        return $properties;
    }

    function access() { return $this->access; }

    function className() { return $this->className; }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("$this->access ");
    }

    function schema() {
        $schema = parent::schema();
        $schema->bindRef('className', $this->className);
        $schema->bindRef('access', $this->access);
        $schema->bindRef('isDefault', $this->isDefault);

        return $schema;
    }
}

class ValueObjectPropertyStatic extends ValueObjectProperty {
    static protected function create() { return new self; }

    static function introspectStaticProperties(Introspection $i) {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $property->setAccessible(true);

                $globals[] = self::introspectObjectProperty($i, $property->name,
                                                            ref_new($property->getValue()), $property);
            }
        }

        return $globals;
    }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("{$this->access()} static {$this->className()}::");
    }
}
