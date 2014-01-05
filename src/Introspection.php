<?php

namespace ErrorHandler;

class Introspection {
    /** @var ValueObject[] */
    public $objectCache = array();
    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    public $objects = array();
    /** @var IntrospectionArrayCacheEntry[] */
    public $arrayCache = array();

    /**
     * @param \ReflectionProperty|\ReflectionMethod $property
     *
     * @throws \Exception
     * @return string
     */
    function propertyOrMethodAccess($property) {
        if ($property->isPublic())
            return 'public';
        else if ($property->isPrivate())
            return 'private';
        else if ($property->isProtected())
            return 'protected';
        else
            throw new \Exception("This thing is not protected, public, nor private? Huh?");
    }

    function introspectException(\Exception $e) {
        return ValueException::introspectImpl($this, $e);
    }

    function introspect($x) {
        return $this->introspectRef($x);
    }

    /**
     * @param mixed $x
     *
     * @return Value
     */
    function introspectRef(&$x) {
        if (is_string($x))
            return new ValueString($x);
        else if (is_int($x))
            return new ValueInt($x);
        else if (is_bool($x))
            return new ValueBool($x);
        else if (is_null($x))
            return new ValueNull;
        else if (is_float($x))
            return new ValueFloat($x);
        else if (is_array($x))
            return ValueArray::introspectImpl($this, $x);
        else if (is_object($x))
            return ValueObject::introspectImpl($this, $x);
        else if (is_resource($x))
            return ValueResource::introspectImpl($x);
        else
            return new ValueUnknown;
    }
}

class IntrospectionArrayCacheEntry {
    /** @var array */
    public $array;
    /** @var ValueArray */
    public $result;
}
