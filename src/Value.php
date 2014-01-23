<?php

namespace ErrorHandler;

interface ValueVisitor {
    function visitObject(ValueObject $o);

    function visitArray(ValueArray $a);

    function visitException(ValueException $e);

    function visitString(ValueString $s);

    /**
     * @param int $int
     *
     * @return mixed
     */
    function visitInt($int);

    function visitNull();

    function visitUnknown();

    /**
     * @param float $float
     *
     * @return mixed
     */
    function visitFloat($float);

    function visitResource(ValueResource $r);

    /**
     * @param bool $bool
     *
     * @return mixed
     */
    function visitBool($bool);
}

abstract class Value {
    function toJsonFromJson() {
        return JSONParse::fromJSON(JSONUnparse::toJSON($this));
    }

    abstract function acceptVisitor(ValueVisitor $visitor);
}

class ValueBool extends Value {
    private $bool;

    function __construct($x) {
        assert(is_bool($x));

        $this->bool = $x;
    }

    function bool() { return $this->bool; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitBool($this->bool); }
}

class ValueFloat extends Value {
    private $float;

    function __construct($x) {
        assert(is_float($x));

        $this->float = $x;
    }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitFloat($this->float); }

    function float() { return $this->float; }
}

class ValueInt extends Value {
    private $int;

    function __construct($x) {
        assert(is_int($x));

        $this->int = $x;
    }

    function int() { return $this->int; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitInt($this->int); }
}

class ValueNull extends Value {
    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitNull(); }
}

class ValueResource extends Value {
    private $type, $id;

    function __construct($type, $id) {
        $this->type = $type;
        $this->id   = $id;
    }

    function resourceType() { return $this->type; }

    function resourceID() { return $this->id; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitResource($this); }
}

class ValueString extends Value {
    private $string;

    function __construct($x) {
        assert(is_string($x));

        $this->string = $x;
    }

    function string() { return $this->string; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitString($this); }
}

class ValueUnknown extends Value {
    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitUnknown(); }
}


