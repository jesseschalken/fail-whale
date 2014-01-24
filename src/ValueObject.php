<?php

namespace ErrorHandler;

class ValueObject implements Value {
    private $hash;
    private $class;
    /** @var ValueObjectProperty[] */
    private $properties = array();
    private $id;

    function className() { return $this->class; }

    function properties() { return $this->properties; }

    function setHash($hash) { $this->hash = $hash; }

    function setClass($class) { $this->class = $class; }

    function addProperty(ValueObjectProperty $p) { $this->properties[] = $p; }

    function acceptVisitor(ValueVisitor $visitor) { return $visitor->visitObject($this); }

    function getHash() { return $this->hash; }

    function id() { return $this->id; }

    function setId($id) { $this->id = $id; }
}

class ValueObjectProperty extends ValueVariable {
    /**
     * @param Introspection $i
     *
     * @return self[]
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

class ValueObjectPropertyStatic extends ValueObjectProperty {
    static protected function create() { return new self; }

    function renderPrefix(PrettyPrinter $settings) {
        return $settings->text("{$this->access()} static {$this->className()}::");
    }
}
