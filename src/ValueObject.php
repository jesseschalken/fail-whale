<?php

namespace ErrorHandler;

class ValueObject extends Value {
    function introspectImpl(Introspection $i, &$x) {
        $hash = spl_object_hash($x);

        $a =& $i->objectCache[$hash];
        if ($a !== null)
            return;
        $a = $this;

        $i->objects[]     = $x;
        $this->hash       = $hash;
        $this->className  = get_class($x);
        $this->properties = ValueVariable::introspectObjectProperties($i, $x);
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

    function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('className', $this->className);
        $schema->bindRef('hash', $this->hash);
        $schema->bindObjectList('properties', $this->properties, function () { return new ValueVariable; });

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

    function fromJSON(JsonDeSerializationState $s, $x) {
        $object =& $s->finishedObjects[$x[1]];

        if ($object !== $this) {
            $object = $this;
            $this->schema()->fromJSON($s, $s->root['objects'][$x[1]]);
        }
    }
}

