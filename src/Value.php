<?php

namespace ErrorHandler;

abstract class Value implements JsonSerializable {
    static function introspect($x) {
        return self::i()->introspect($x);
    }

    static function introspectRef(&$x) {
        return self::i()->introspectRef($x);
    }

    static function introspectException(\Exception $e) {
        return self::i()->introspectException($e);
    }

    static function fromJsonWhole($v) {
        $d       = new JsonDeSerializationState;
        $d->root = $v;
        $self    = $d->constructValue($v['root']);
        $self->fromJSON($d, $v['root']);

        return $self;
    }

    private static function i() {
        return new Introspection;
    }

    private static $nextID = 0;
    private $id;

    function __construct() {
        $this->id = self::$nextID++;
    }

    function __clone() {
        $this->id = self::$nextID++;
    }

    function toJsonWhole() {
        $s               = new JsonSerializationState;
        $s->root['root'] = $this->toJSON($s);

        return $s->root;
    }

    function toJsonFromJson() {
        return self::fromJsonWhole($this->toJsonWhole());
    }

    /**
     * @param PrettyPrinter $settings
     *
     * @return PrettyPrinterText
     */
    final function render(PrettyPrinter $settings) {
        return $settings->render($this);
    }

    function id() { return $this->id; }

    /**
     * @param PrettyPrinter $settings
     *
     * @return PrettyPrinterText
     */
    abstract function renderImpl(PrettyPrinter $settings);

    /**
     * @return self[]
     */
    function subValues() { return array(); }

    abstract function introspectImpl(Introspection $i, &$x);
}

class ValueBool extends Value {
    private $bool;

    function introspectImpl(Introspection $i, &$x) {
        assert(is_bool($x));

        $this->bool = $x;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text($this->bool ? 'true' : 'false');
    }

    function toJSON(JsonSerializationState $s) {
        return $this->bool;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->bool = $x;
    }
}

class ValueFloat extends Value {
    private $float;

    /**
     * @param \ErrorHandler\Introspection|float $i
     * @param                                   $x
     */
    function introspectImpl(Introspection $i, &$x) {
        assert(is_float($x));

        $this->float = $x;
    }

    function renderImpl(PrettyPrinter $settings) {
        $int = (int)$this->float;

        return $settings->text("$int" === "$this->float" ? "$this->float.0" : "$this->float");
    }

    function toJSON(JsonSerializationState $s) {
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

    function fromJSON(JsonDeSerializationState $s, $x2) {
        $x = $x2[1];
        if ($x === '+inf')
            $result = INF;
        else if ($x === '-inf')
            $result = -INF;
        else if ($x === 'nan')
            $result = NAN;
        else
            $result = (float)$x;
        $this->float = $result;
    }
}

class JsonConst implements JsonSerializable {
    private $const;

    function __construct($const) {
        $this->const = $const;
    }

    function toJSON(JsonSerializationState $s) {
        return $this->const;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
    }
}

class ValueInt extends Value {
    private $int;

    function introspectImpl(Introspection $i, &$x) {
        assert(is_int($x));

        $this->int = $x;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text("$this->int");
    }

    function toJSON(JsonSerializationState $s) {
        return $this->int;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->int = $x;
    }
}

class ValueNull extends Value {
    function renderImpl(PrettyPrinter $settings) {
        return $settings->text('null');
    }

    function introspectImpl(Introspection $i, &$x) {
    }

    function toJSON(JsonSerializationState $s) {
        return null;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
    }
}

class ValueResource extends Value {
    function introspectImpl(Introspection $i, &$x) {
        $this->type = get_resource_type($x);
        $this->id   = (int)$x;
    }

    private $type;
    private $id;

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text($this->type);
    }

    function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('type', $this->type);
        $schema->bindRef('id', $this->id);

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        return array('resource', $this->schema()->toJSON($s));
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->schema()->fromJSON($s, $x[1]);
    }
}

class ValueString extends Value {
    private $string;

    function introspectImpl(Introspection $i, &$x) {
        assert(is_string($x));

        $this->string = $x;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderString($this->string);
    }

    function toJSON(JsonSerializationState $s) {
        return $this->string;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        $this->string = $x;
    }
}

class ValueUnknown extends Value {
    function renderImpl(PrettyPrinter $settings) {
        return $settings->text('unknown type');
    }

    function introspectImpl(Introspection $i, &$x) {
    }

    function toJSON(JsonSerializationState $s) {
        return array('unknown');
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
    }
}


