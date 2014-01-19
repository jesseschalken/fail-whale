<?php

namespace ErrorHandler;

class ValueObject extends Value {
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

    function className() { return $this->class; }

    function properties() { return $this->properties; }

    function toJSON(JSONSerialize $s) {
        $id =& $s->objectIndexes[$this->id()];

        if ($id === null) {
            $id = count($s->root['objects']);

            $json =& $s->root['objects'][$id];
            $json = $this->schema()->toJSON($s);
        }

        return array('object', $id);
    }

    function setHash($hash) { $this->hash = $hash; }

    function setClass($class) { $this->class = $class; }

    function addProperty(ValueObjectProperty $p) { $this->properties[] = $p; }

    private function schema() {
        $schema = new JSONSchema;
        $schema->bind('class', $this->class);
        $schema->bind('hash', $this->hash);

        $schema->bindObjectList('properties', $this->properties, function ($j, $v) {
            return ValueObjectProperty::fromJSON($j, $v);
        });

        return $schema;
    }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitObject($this); }
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

    static protected function create() { return new self; }

    private $class;
    private $access;
    private $isDefault;

    function setClass($x) { $this->class = $x; }

    function setIsDefault($x) { $this->isDefault = $x; }

    function setAccess($x) { $this->access = $x; }

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
    static protected function create() { return new self; }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("{$this->access()} static {$this->className()}::");
    }
}
