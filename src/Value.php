<?php

namespace ErrorHandler;

abstract class Value {
    static function introspect($x) {
        return self::i()->introspect($x);
    }

    static function introspectRef(&$x) {
        return self::i()->introspectRef($x);
    }

    static function introspectException(\Exception $e) {
        return self::i()->introspectException($e);
    }

    static function fromJsonValue($x) {
        return JsonSerialize::fromJsonWhole($x);
    }

    static function fromJson($json) {
        return self::fromJsonValue(Json::parse($json));
    }

    private static function i() {
        return new Introspection;
    }

    private static $nextID = 0;
    private $id;

    protected function __construct() {
        $this->id = self::$nextID++;
    }

    /**
     * @param PrettyPrinter $settings
     *
     * @return PrettyPrinterText
     */
    final function render(PrettyPrinter $settings) {
        return $settings->render($this);
    }

    abstract function toJsonValueImpl(JsonSerialize $s);

    final function toJsonValue() {
        return JsonSerialize::toJsonWhole($this);
    }

    final function toJson() {
        return Json::stringify($this->toJsonValue());
    }

    function toJsonFromJson() {
        return self::fromJson($this->toJson());
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

    /**
     * @param bool $bool
     */
    function __construct($bool) {
        assert(is_bool($bool));

        $this->bool = $bool;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text($this->bool ? 'true' : 'false');
    }

    function toJsonValueImpl(JsonSerialize $s) {
        return array(
            'type' => 'bool',
            'bool' => $this->bool,
        );
    }
}

class ValueFloat extends Value {
    private $float;

    /**
     * @param float $float
     */
    function __construct($float) {
        assert(is_float($float));

        $this->float = $float;
    }

    function renderImpl(PrettyPrinter $settings) {
        $int = (int)$this->float;

        return $settings->text("$int" === "$this->float" ? "$this->float.0" : "$this->float");
    }

    function toJsonValueImpl(JsonSerialize $s) {
        if ($this->float === INF)
            return array('type' => '+inf');

        if ($this->float === -INF)
            return array('type' => '-inf');

        if (is_nan($this->float))
            return array('type' => 'nan');

        return array(
            'type'  => 'float',
            'float' => $this->float,
        );
    }
}

class ValueInt extends Value {
    private $int;

    /**
     * @param int $int
     */
    function __construct($int) {
        assert(is_int($int));

        $this->int = $int;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text("$this->int");
    }

    function toJsonValueImpl(JsonSerialize $s) {
        return array(
            'type' => 'int',
            'int'  => $this->int,
        );
    }
}

class ValueNull extends Value {
    function renderImpl(PrettyPrinter $settings) {
        return $settings->text('null');
    }

    function toJsonValueImpl(JsonSerialize $s) {
        return array(
            'type' => 'null',
        );
    }

    function __construct() {
        parent::__construct();
    }
}

class ValueResource extends Value {
    /**
     * @param resource $value
     *
     * @return self
     */
    static function introspectImpl($value) {
        $self       = new self;
        $self->type = get_resource_type($value);
        $self->id   = (int)$value;

        return $self;
    }

    private $type;
    private $id;

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text($this->type);
    }

    function toJsonValueImpl(JsonSerialize $s) {
        return array(
            'type'     => 'resource',
            'resource' => array(
                'resourceType' => $this->type,
                'resourceId'   => $this->id,
            ),
        );
    }

    static function fromJsonValueImpl(array $v) {
        $self       = new self;
        $self->type = $v['resourceType'];
        $self->id   = $v['resourceId'];

        return $self;
    }
}

class ValueString extends Value {
    private $string;

    /**
     * @param string $string
     */
    function __construct($string) {
        assert(is_string($string));

        $this->string = $string;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderString($this->string);
    }

    function toJsonValueImpl(JsonSerialize $s) {
        return array(
            'type'   => 'string',
            'string' => $this->string,
        );
    }
}

class ValueUnknown extends Value {
    function renderImpl(PrettyPrinter $settings) {
        return $settings->text('unknown type');
    }

    function toJsonValueImpl(JsonSerialize $s) {
        return array(
            'type' => 'unknown',
        );
    }

    function __construct() {
        parent::__construct();
    }
}


