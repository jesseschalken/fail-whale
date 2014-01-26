<?php

namespace ErrorHandler;

interface ValueObject {
    /** @return string */
    function className();

    /** @return ValueObjectProperty[] */
    function properties();

    /** @return string */
    function getHash();

    /** @return int */
    function id();
}

class MutableValueObjectProperty extends MutableValueVariable implements ValueObjectProperty {
    /**
     * @param Introspection $i
     *
     * @return MutableValueObjectProperty[]
     */
    static function mockStatic(Introspection $i) {
        $self            = static::introspect($i, 'blahProperty', ref_new());
        $self->class     = 'BlahClass';
        $self->access    = 'private';
        $self->isDefault = false;
        $globals[]       = $self;

        return $globals;
    }

    static protected function create() { return new self; }

    private $class;
    private $access;
    private $isDefault;

    function setClass($x) { $this->class = $x; }

    function setIsDefault($x) { $this->isDefault = $x; }

    function setAccess($x) { $this->access = $x; }

    function access() { return $this->access; }

    function className() { return $this->class; }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("$this->access ");
    }

    function isDefault() { return $this->isDefault; }
}

class ValueObjectPropertyStatic extends MutableValueObjectProperty {
    static protected function create() { return new self; }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("{$this->access()} static {$this->className()}::");
    }
}
