<?php

namespace ErrorHandler;

interface ValueVisitor {
    function visitObject(ValueObject $o);

    function visitArray(ValueArray $a);

    function visitException(ValueException $e);

    function visitString(ValueString $s);

    function visitInt(ValueInt $i);

    function visitNull(ValueNull $n);

    function visitUnknown(ValueUnknown $u);

    function visitFloat(ValueFloat $f);

    function visitResource(ValueResource $r);

    function visitBool(ValueBool $b);
}

class FindSubValues implements ValueVisitor {
    function visitObject(ValueObject $o) {
        $x = array();

        foreach ($o->properties() as $p) {
            $x[] = $p->value();
        }

        return $x;
    }

    function visitArray(ValueArray $a) {
        $x = array();

        foreach ($a->entries() as $kvPair) {
            $x[] = $kvPair->key();
            $x[] = $kvPair->value();
        }

        return $x;
    }

    function visitException(ValueException $e) {
        $x = array();

        if ($e->locals() !== null) {
            foreach ($e->locals() as $local) {
                $x[] = $local->value();
            }
        }

        if ($e->globals() !== null) {
            foreach ($e->globals()->variables() as $global) {
                $x[] = $global->value();
            }
        }

        foreach ($e->stack() as $frame) {
            if ($frame->getObject() !== null) {
                $x[] = $frame->getObject();
            }

            if ($frame->getArgs() !== null) {
                foreach ($frame->getArgs() as $c) {
                    $x[] = $c;
                }
            }
        }

        if ($e->previous() !== null) {
            foreach ($this->visitException($e->previous()) as $c) {
                $x[] = $c;
            }
        }

        return $x;
    }

    function visitString(ValueString $s) { return array(); }

    function visitInt(ValueInt $i) { return array(); }

    function visitNull(ValueNull $n) { return array(); }

    function visitUnknown(ValueUnknown $u) { return array(); }

    function visitFloat(ValueFloat $f) { return array(); }

    function visitResource(ValueResource $r) { return array(); }

    function visitBool(ValueBool $b) { return array(); }
}

abstract class Value {
    private static $nextID = 0;

    private $id;

    function __construct() {
        $this->id = self::$nextID++;
    }

    function __clone() {
        $this->id = self::$nextID++;
    }

    function toJsonFromJson() {
        return JSONParse::fromJSON(JSONUnparse::toJSON($this));
    }

    /**
     * @param PrettyPrinter $settings
     *
     * @return PrettyPrinterText
     */
    final function render(PrettyPrinter $settings) { return $settings->render($this); }

    function id() { return $this->id; }

    abstract function acceptVisitor(ValueVisitor $visitor);
}

class ValueBool extends Value {
    private $bool;

    function __construct($x) {
        assert(is_bool($x));

        $this->bool = $x;
    }

    function bool() { return $this->bool; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitBool($this); }
}

class ValueFloat extends Value {
    private $float;

    function __construct($x) {
        assert(is_float($x));

        $this->float = $x;
    }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitFloat($this); }

    function float() { return $this->float; }
}

class ValueInt extends Value {
    private $int;

    function __construct($x) {
        assert(is_int($x));

        $this->int = $x;
    }

    function int() { return $this->int; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitInt($this); }
}

class ValueNull extends Value {
    function __construct() { parent::__construct(); }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitNull($this); }
}

class ValueResource extends Value {
    private $type;
    private $id;

    function type() { return $this->type; }

    function setType($type) { $this->type = $type; }

    function getType() { return $this->type; }

    function setResourceId($id) { $this->id = $id; }

    function getResourceId() { return $this->id; }

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
    function __construct() { parent::__construct(); }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitUnknown($this); }
}


