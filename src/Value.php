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

        foreach ($o->properties() as $p)
            $x[] = $p->value();

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

        if ($e->locals() !== null)
            foreach ($e->locals() as $local)
                $x[] = $local->value();

        if ($e->globals() !== null)
            foreach ($e->globals()->variables() as $global)
                $x[] = $global->value();

        foreach ($e->stack() as $frame)
            foreach ($frame->subValues() as $c)
                $x[] = $c;

        if ($e->previous() !== null)
            foreach ($this->visitException($e->previous()) as $c)
                $x[] = $c;

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

abstract class Value implements JSONSerializable {
    private static $nextID = 0;

    /**
     * @param JSONUnserialize $s
     * @param                 $v
     *
     * @return self
     * @throws Exception
     */
    static function fromJSON(JSONUnserialize $s, $v) {
        if (is_float($v))
            return new ValueFloat($v);

        if (is_int($v))
            return new ValueInt($v);

        if (is_bool($v))
            return new ValueBool($v);

        if (is_null($v))
            return new ValueNull;

        if (is_string($v))
            return new ValueString($v);

        switch ($v[0]) {
            case 'object':
                return ValueObject::fromJSON($s, $v);
            case '-inf':
            case '+inf':
            case 'nan':
            case 'float':
                return ValueFloat::fromJSON($s, $v);
            case 'array':
                return ValueArray::fromJSON($s, $v);
            case 'exception':
                return ValueException::fromJSON($s, $v);
            case 'resource':
                return ValueResource::fromJSON($s, $v);
            case 'unknown':
                return new ValueUnknown;
            case 'null':
                return new ValueNull;
            case 'int':
                return new ValueInt($v[1]);
            case 'bool':
                return new ValueBool($v[1]);
            case 'string':
                return new ValueString($v[1]);
            default:
                throw new Exception("Unknown type: {$v[0]}");
        }
    }

    private $id;

    function __construct() {
        $this->id = self::$nextID++;
    }

    function __clone() {
        $this->id = self::$nextID++;
    }

    function toJsonFromJson() {
        return JSONUnserialize::fromJSON(JSONSerialize::toJSON($this));
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

    function toJSON(JSONSerialize $s) { return $this->bool; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitBool($this); }
}

class ValueFloat extends Value {
    /**
     * @param JSONUnserialize $s
     * @param mixed           $x2
     *
     * @return self
     */
    static function fromJSON(JSONUnserialize $s, $x2) {
        $x = $x2[1];
        if ($x === '+inf')
            $result = INF;
        else if ($x === '-inf')
            $result = -INF;
        else if ($x === 'nan')
            $result = NAN;
        else
            $result = (float)$x;

        return new self($result);
    }

    private $float;

    function __construct($x) {
        assert(is_float($x));

        $this->float = $x;
    }

    function toJSON(JSONSerialize $s) {
        $result = $this->float;

        if ($result === INF)
            $float = '+inf';
        else if ($result === -INF)
            $float = '-inf';
        else if (is_nan($result))
            $float = 'nan';
        else
            $float = $result;

        return array('float', $float);
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

    function toJSON(JSONSerialize $s) { return $this->int; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitInt($this); }
}

class ValueNull extends Value {
    function __construct() { parent::__construct(); }

    function toJSON(JSONSerialize $s) { return null; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitNull($this); }
}

class ValueResource extends Value {
    static function fromJSON(JSONUnserialize $s, $x) {
        $self = new self;
        $self->schema()->fromJSON($s, $x[1]);

        return $self;
    }

    private $type;
    private $id;

    function toJSON(JSONSerialize $s) {
        return array('resource', $this->schema()->toJSON($s));
    }

    function type() { return $this->type; }

    function setType($type) { $this->type = $type; }

    function setId($id) { $this->id = $id; }

    private function schema() {
        $schema = new JSONSchema;
        $schema->bind('type', $this->type);
        $schema->bind('id', $this->id);

        return $schema;
    }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitResource($this); }
}

class ValueString extends Value {
    private $string;

    function __construct($x) {
        assert(is_string($x));

        $this->string = $x;
    }

    function string() { return $this->string; }

    function toJSON(JSONSerialize $s) { return $this->string; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitString($this); }
}

class ValueUnknown extends Value {
    function __construct() { parent::__construct(); }

    function toJSON(JSONSerialize $s) { return array('unknown'); }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitUnknown($this); }
}


