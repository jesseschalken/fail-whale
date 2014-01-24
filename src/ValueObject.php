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

class MutableValueObject implements Value, ValueObject {
    private $hash;
    private $class;
    /** @var MutableValueObjectProperty[] */
    private $properties = array();
    private $id;

    function className() { return $this->class; }

    function properties() { return $this->properties; }

    function setHash($hash) { $this->hash = $hash; }

    function setClass($class) { $this->class = $class; }

    function addProperty(MutableValueObjectProperty $p) { $this->properties[] = $p; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitObject($this); }

    function getHash() { return $this->hash; }

    function id() { return $this->id; }

    function setId($id) { $this->id = $id; }
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
