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
        if ($property->isPrivate())
            return 'private';
        if ($property->isProtected())
            return 'protected';

        throw new \Exception("This thing is not protected, public, nor private? Huh?");
    }

    function introspectException(\Exception $e) {
        $result = new ValueException;
        $result->introspectImpl($this, $e);

        return $result;
    }

    function introspect($x) {
        return $this->introspectRef($x);
    }

    function introspectRef(&$x) {
        $value = $this->create($x);
        $value->introspectImpl($this, $x);

        return $value;
    }

    /**
     * @param $x
     *
     * @return Value
     */
    function create(&$x) {
        if (is_string($x))
            return new ValueString;

        if (is_int($x))
            return new ValueInt;

        if (is_bool($x))
            return new ValueBool;

        if (is_null($x))
            return new ValueNull;

        if (is_float($x))
            return new ValueFloat;

        if (is_array($x)) {
            foreach ($this->arrayCache as $entry)
                if (ref_equal($entry->array, $x))
                    return $entry->result;

            return new ValueArray;
        }

        if (is_object($x)) {
            $result =& $this->objectCache[spl_object_hash($x)];

            return $result === null ? new ValueObject : $result;
        }

        if (is_resource($x))
            return new ValueResource;

        return new ValueUnknown;
    }
}

class IntrospectionArrayCacheEntry {
    /** @var array */
    public $array;
    /** @var ValueArray */
    public $result;
}
