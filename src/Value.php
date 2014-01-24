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

interface Value {
    function acceptVisitor(ValueVisitor $visitor);
}

class ValueResource implements Value {
    private $type, $id;

    function __construct($type, $id) {
        $this->type = $type;
        $this->id   = $id;
    }

    function resourceType() { return $this->type; }

    function resourceID() { return $this->id; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitResource($this); }
}

class ValueString implements Value {
    private $string;

    function __construct($x) {
        assert(is_string($x));

        $this->string = $x;
    }

    function string() { return $this->string; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitString($this); }
}
