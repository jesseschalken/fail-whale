<?php

namespace ErrorHandler;

class ValueObject extends Value {
    static function introspect(Introspection $i, &$x) {
        $hash = spl_object_hash($x);
        $self =& $i->objectCache[$hash];

        if ($self !== null)
            return $self;

        $i->objects[] = $x;

        $self             = new self;
        $self->hash       = $hash;
        $self->class      = get_class($x);
        $self->properties = ValueObjectProperty::introspectObjectProperties($i, $x);

        return $self;
    }

    static function fromJSON(JSONUnserialize $s, $x) {
        if ($x === null)
            return null;

        $self =& $s->finishedObjects[$x[1]];

        if ($self === null) {
            $self = new self;
            $self->schema()->fromJSON($s, $s->root['objects'][$x[1]]);
        }

        return $self;
    }

    private $hash;
    private $class;
    /** @var ValueObjectProperty[] */
    private $properties = array();

    function subValues() {
        $x = parent::subValues();

        foreach ($this->properties as $p)
            $x[] = $p->value();

        return $x;
    }

    function className() { return $this->class; }

    function properties() { return $this->properties; }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderObject($this);
    }

    function toJSON(JSONSerialize $s) {
        $id =& $s->objectIndexes[$this->id()];

        if ($id === null) {
            $id = count($s->root['objects']);

            $json =& $s->root['objects'][$id];
            $json = $this->schema()->toJSON($s);
        }

        return array('object', $id);
    }

    private function schema() {
        $schema = new JSONSchema;
        $schema->bind('class', $this->class);
        $schema->bind('hash', $this->hash);

        $schema->bindObjectList('properties', $this->properties, function ($j, $v) {
            return ValueObjectProperty::fromJSON($j, $v);
        });

        return $schema;
    }
}

class ValueObjectProperty extends ValueVariable {
    /**
     * @param Introspection $i
     *
     * @return self[]
     */
    static function mockStatic(Introspection $i) {
        $self            = static::introspect($i, 'blahProperty', ref_new());
        $self->class     = 'BlahClass';
        $self->access    = 'private';
        $self->isDefault = false;
        $globals[]       = $self;

        return $globals;
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
                if (!$property->isStatic() && $property->class === $reflection->name) {
                    $properties[] = self::introspectObjectProperty($i, $property, $object);
                }
            }
        }

        return $properties;
    }

    static protected function create() { return new self; }

    protected static function introspectObjectProperty(Introspection $i, \ReflectionProperty $p, $object = null) {
        $p->setAccessible(true);

        $self            = static::introspect($i, $p->name, ref_new($p->getValue($object)));
        $self->class     = $p->class;
        $self->access    = self::accessAsString($p);
        $self->isDefault = $p->isDefault();

        return $self;
    }
    
    private static function accessAsString(\ReflectionProperty $property) {
        if ($property->isPublic())
            return 'public';
        else if ($property->isPrivate())
            return 'private';
        else if ($property->isProtected())
            return 'protected';
        else
            throw new \Exception("This thing is not protected, public, nor private? Huh?");
    }

    private $class;
    private $access;
    private $isDefault;

    function access() { return $this->access; }

    function className() { return $this->class; }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("$this->access ");
    }

    function schema() {
        $schema = parent::schema();
        $schema->bind('class', $this->class);
        $schema->bind('access', $this->access);
        $schema->bind('isDefault', $this->isDefault);

        return $schema;
    }
}

class ValueObjectPropertyStatic extends ValueObjectProperty {
    static function introspectStaticProperties(Introspection $i) {
        $globals = array();

        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                $globals[] = self::introspectObjectProperty($i, $property);
            }
        }

        return $globals;
    }

    static protected function create() { return new self; }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("{$this->access()} static {$this->className()}::");
    }
}
