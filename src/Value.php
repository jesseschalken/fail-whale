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

        return self::fromJson($d, $v['root']);
    }

    private static function i() {
        return new Introspection;
    }

    static function fromJson(JsonDeSerializationState $s, $v) {
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
                return ValueObject::fromJson($s, $v);
            case '-inf':
            case '+inf':
            case 'nan':
            case 'float':
                return ValueFloat::fromJson($v);
            case 'array':
                return ValueArray::fromJson($s, $v);
            case 'exception':
                return ValueException::fromJson($s, $v);
            case 'resource':
                return ValueResource::fromJson($s, $v);
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

    private static $nextID = 0;
    private $id;

    protected function __construct() {
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
        return self::fromJsonWhole(Json::parse(Json::stringify($this->toJsonWhole())));
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
}

class ValueBool extends Value {
    private $bool;

    function __construct($x) {
        assert(is_bool($x));

        $this->bool = $x;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text($this->bool ? 'true' : 'false');
    }

    function toJSON(JsonSerializationState $s) {
        return $this->bool;
    }
}

class ValueFloat extends Value {
    private $float;

    function __construct($x) {
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

    /**
     * @param mixed $x2
     *
     * @return self
     */
    static function fromJSON($x2) {
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
}

class ValueInt extends Value {
    private $int;

    function __construct($x) {
        assert(is_int($x));

        $this->int = $x;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text("$this->int");
    }

    function toJSON(JsonSerializationState $s) {
        return $this->int;
    }
}

class ValueNull extends Value {
    function renderImpl(PrettyPrinter $settings) {
        return $settings->text('null');
    }

    function toJSON(JsonSerializationState $s) {
        return null;
    }

    function __construct() { parent::__construct(); }
}

class ValueResource extends Value {
    static function introspectImpl($x) {
        $self       = new self;
        $self->type = get_resource_type($x);
        $self->id   = (int)$x;

        return $self;
    }

    private $type;
    private $id;

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text($this->type);
    }

    private function schema() {
        $schema = new JsonSchemaObject;
        $schema->bindRef('type', $this->type);
        $schema->bindRef('id', $this->id);

        return $schema;
    }

    function toJSON(JsonSerializationState $s) {
        return array('resource', $this->schema()->toJSON($s));
    }

    static function fromJSON(JsonDeSerializationState $s, $x) {
        $self = new self;
        $self->schema()->fromJSON($s, $x[1]);

        return $self;
    }
}

class ValueString extends Value {
    private $string;

    function __construct($x) {
        assert(is_string($x));

        $this->string = $x;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderString($this->string);
    }

    function toJSON(JsonSerializationState $s) {
        return $this->string;
    }
}

class ValueUnknown extends Value {
    function renderImpl(PrettyPrinter $settings) {
        return $settings->text('unknown type');
    }

    function __construct() { parent::__construct(); }

    function toJSON(JsonSerializationState $s) {
        return array('unknown');
    }
}


