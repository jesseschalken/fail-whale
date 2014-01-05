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
        $self->properties = ValueVariable::introspectObjectProperties($i, $x);

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

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('className', $this->className);
        $schema->bindRef('hash', $this->hash);
        $schema->bindObjectList('properties', $this->properties, function ($j, $v) { return ValueVariable::fromJSON($j, $v); });

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        $id =& $s->objectIDs[$this->id()];

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

