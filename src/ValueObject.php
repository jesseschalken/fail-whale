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
        return new JsonSchemaObject(
            array(
                'type'   => new JsonConst('object'),
                'object' => new JsonObjectID($this),
            )
        );
    }

    function wholeSchema() {
        return new JsonSchemaObject(
            array(
                'className'  => new JsonRef($this->className),
                'hash'       => new JsonRef($this->hash),
                'properties' => new JsonRefObjectList($this->properties, function () { return new ValueVariable; }),
            )
        );
    }
}

class JsonObjectID extends JsonSchema {
    /** @var ValueObject */
    private $o;

    function __construct(ValueObject $o) {
        $this->o = $o;
    }

    function toJSON(JsonSerializationState $s) {
        $id =& $s->objectIDs[$this->o->id()];
        if ($id !== null)
            return $id;
        $id = count($s->root['objects']);

        $s->root['objects'][$id] = $this->o->wholeSchema()->toJSON($s);

        return $id;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $object =& $s->finishedObjects[$x];
        if ($object !== null)
            return;
        $object = $this->o;
        $object->wholeSchema()->fromJSON($s, $s->root['objects'][$x]);
    }
}
