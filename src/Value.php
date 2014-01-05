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
        assert(is_bool($i));

        $this->bool = $i;
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->text($this->bool ? 'true' : 'false');
    }

    function schema() {
        return new JsonRef($this->bool);
    }
}

class ValueFloat extends Value {
    private $float;

    /**
     * @param \ErrorHandler\Introspection|float $i
     * @param                                   $x
     */
    function introspectImpl(Introspection $i, &$x) {
        assert(is_float($i));

        $this->float = $i;
    }

    function renderImpl(PrettyPrinter $settings) {
        $int = (int)$this->float;

        return $settings->text("$int" === "$this->float" ? "$this->float.0" : "$this->float");
    }

    function schema() {
        return new JsonSchemaObject(
            array(
                'type'  => new JsonConst('float'),
                'float' => new JsonFloatRef($this->float),
            )
        );
    }
}

class JsonFloatRef extends JsonRef {
    function toJSON(JsonSerializationState $s) {
        $result = $this->get();

        if ($result === INF)
            return '+inf';
        else if ($result === -INF)
            return '-inf';
        else if (is_nan($result))
            return 'nan';
        else
            return $result;
    }

    function fromJSON(JsonDeSerializationState $s, $x) {
        if ($x === '+inf')
            $result = INF;
        else if ($x === '-inf')
            $result = -INF;
        else if ($x === 'nan')
            $result = NAN;
        else
            $result = (float)$x;

        $this->set($result);
    }

}

class JsonConst extends JsonSchema {
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

    function schema() {
        return new JsonRef($this->int);
    }
}

class ValueNull extends Value {
    function renderImpl(PrettyPrinter $settings) {
        return $settings->text('null');
    }

    function introspectImpl(Introspection $i, &$x) {
    }

    function schema() {
        return new JsonConst(null);
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

    /**
     * @return JsonSchema
     */
    function schema() {
        return new JsonSchemaObject(
            array(
                'type'     => new JsonConst('resource'),
                'resource' => new JsonSchemaObject(
                        array(
                            'type' => new JsonRef($this->type),
                            'id'   => new JsonRef($this->id),
                        )
                    ),
            )
        );
    }
}

class ValueString extends Value {
    private $string;

    function introspectImpl(Introspection $i, &$x) {
        assert(is_string($x));

        $this->string = $x;
    }

    /**
     * @return JsonSchema
     */
    function schema() {
        return new JsonRef($this->string);
    }

    function renderImpl(PrettyPrinter $settings) {
        return $settings->renderString($this->string);
    }
}

class ValueUnknown extends Value {
    function renderImpl(PrettyPrinter $settings) {
        return $settings->text('unknown type');
    }

    function introspectImpl(Introspection $i, &$x) {
    }

    /**
     * @return JsonSchema
     */
    function schema() {
        return new JsonSchemaObject(
            array(
                'type' => new JsonConst('unknown'),
            )
        );
    }
}


