<?php

namespace ErrorHandler;

class Introspection {
    static function introspect($x) { return self::introspectRef($x); }

    static function introspectRef(&$x) { return Value::introspect(new self, $x); }

    static function introspectException(\Exception $e) { return ValueException::introspect(new self, $e); }

    static function mockException() { return ValueException::mock(new self); }

    /** @var ValueObject[] */
    public $objectCache = array();
    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    public $objects = array();
    /** @var IntrospectionArrayCacheEntry[] */
    public $arrayCache = array();

    private function __construct() { }
}

class IntrospectionArrayCacheEntry {
    static function add(Introspection $i, ValueArray $x, array &$a) {
        $self            = new self;
        $self->result    = $x;
        $self->array     =& $a;
        $i->arrayCache[] = $self;

        return $x;
    }

    /** @var array */
    private $array;
    /** @var ValueArray */
    private $result;

    private function __construct() { }

    function result() { return $this->result; }

    function isSame(array &$x) { return ref_equal($x, $this->array); }
}
