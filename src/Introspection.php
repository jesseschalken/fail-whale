<?php

namespace ErrorHandler;

class Introspection {
    private $objectCache, $arrayCache;

    function __construct() {
        $this->objectCache = new IntrospectionObjectCache;
        $this->arrayCache  = new IntrospectionArrayCache;
    }

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
        return ValueException::introspectImpl($this, $e);
    }

    function introspect($x) {
        return $this->introspectRef($x);
    }

    function introspectRef(&$x) {
        if (is_string($x))
            return new ValueString($x);

        if (is_int($x))
            return new ValueInt($x);

        if (is_bool($x))
            return new ValueBool($x);

        if (is_null($x))
            return new ValueNull;

        if (is_float($x))
            return new ValueFloat($x);

        if (is_array($x))
            return $this->arrayCache->introspect($this, $x);

        if (is_object($x))
            return $this->objectCache->introspect($this, $x);

        if (is_resource($x))
            return ValueResource::introspectImpl($x);

        return new ValueUnknown;
    }
}

class IntrospectionObjectCache {
    /** @var ValueObject[] */
    private $results = array();
    /** @var object[] Just to keep a reference to the objects, because if they get GC'd their hash can get re-used */
    private $objects = array();

    /**
     * @param Introspection $i
     * @param object        $object
     *
     * @return ValueObject
     */
    function introspect(Introspection $i, $object) {
        $hash = spl_object_hash($object);

        if (array_key_exists($hash, $this->results))
            return $this->results[$hash];

        return ValueObject::introspectImpl($i, $hash, $object, $this);
    }

    function insert($object, $hash, ValueObject $result) {
        $this->objects[$hash] = $object;
        $this->results[$hash] = $result;
    }
}

class IntrospectionArrayCache {
    /** @var IntrospectionArrayCacheEntry[] */
    private $entries = array();

    function introspect(Introspection $i, array &$array) {
        foreach ($this->entries as $entry)
            if ($entry->equals($array))
                return $entry->result();

        return ValueArray::introspectImpl($i, $array, $this);
    }

    function insert(&$array, ValueArray $result) {
        $this->entries[] = new IntrospectionArrayCacheEntry($array, $result);
    }
}

class IntrospectionArrayCacheEntry {
    /** @var array */
    private $array;
    /** @var ValueArray */
    private $result;

    function __construct(array &$array, ValueArray $result) {
        $this->array  =& $array;
        $this->result = $result;
    }

    function equals(array &$array) {
        return ref_equal($this->array, $array);
    }

    function result() {
        return $this->result;
    }
}
