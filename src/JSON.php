<?php

namespace ErrorHandler;

final class JSONUnparse implements ValueVisitor {
    /**
     * @param Value $v
     *
     * @return string
     */
    static function toJSON(Value $v) {
        $self               = new self;
        $self->root['root'] = $v->acceptVisitor($self);

        return JSON::stringify($self->root);
    }

    private $root = array(
        'root'    => null,
        'arrays'  => array(),
        'objects' => array(),
    );

    private function __construct() { }

    function visitObject(ValueObject $o = null) {
        if ($o === null)
            return null;

        $json =& $this->root['objects'][$o->id()];

        if ($json === null) {
            $json = array(
                'class'      => $o->className(),
                'hash'       => $o->getHash(),
                'properties' => array(),
            );

            foreach ($o->properties() as $p) {
                $json['properties'][] = array(
                    'name'      => $p->name(),
                    'value'     => $p->value()->acceptVisitor($this),
                    'class'     => $p->className(),
                    'access'    => $p->access(),
                    'isDefault' => $p->isDefault(),
                );
            }
        }

        return array('object', $o->id());
    }

    function visitArray(ValueArray $a) {
        $json =& $this->root['arrays'][$a->id()];

        if ($json === null) {
            $json = array(
                'isAssociative' => $a->isAssociative(),
                'entries'       => array(),
            );

            foreach ($a->entries() as $entry) {
                $json['entries'][] = array(
                    $entry->key()->acceptVisitor($this),
                    $entry->value()->acceptVisitor($this),
                );
            }
        }

        return array('array', $a->id());
    }

    function visitException(ValueException $e) {
        $result = array(
            'class'    => $e->className(),
            'code'     => $e->code(),
            'message'  => $e->message(),
            'previous' => $e->previous() === null ? null : $this->visitException($e->previous()),
            'location' => $this->locationToJson($e->location()),
            'stack'    => array(),
            'locals'   => array(),
            'globals'  => $this->globalsToJson($e->globals()),
        );

        if ($e->locals() === null) {
            $result['locals'] = null;
        } else {
            foreach ($e->locals() as $var) {
                $result['locals'][] = array(
                    'name'  => $var->name(),
                    'value' => $var->value()->acceptVisitor($this),
                );
            }
        }

        foreach ($e->stack() as $frame) {
            $json = array(
                'function' => $frame->getFunction(),
                'class'    => $frame->getClass(),
                'isStatic' => $frame->getIsStatic(),
                'location' => $this->locationToJson($frame->getLocation()),
                'object'   => $this->visitObject($frame->getObject()),
                'args'     => array(),
            );

            if ($frame->getArgs() === null) {
                $json['args'] = null;
            } else {
                foreach ($frame->getArgs() as $arg) {
                    $json['args'][] = $arg->acceptVisitor($this);
                }
            }

            $result['stack'][] = $json;
        }

        return array('exception', $result);
    }

    function visitString(ValueString $s) { return $s->string(); }

    function visitInt($int) { return $int; }

    function visitNull() { return null; }

    function visitUnknown() { return array('unknown'); }

    function visitFloat($float) {
        if ($float === INF)
            $json = '+inf';
        else if ($float === -INF)
            $json = '-inf';
        else if (is_nan($float))
            $json = 'nan';
        else
            $json = $float;

        return array('float', $json);
    }

    function visitResource(ValueResource $r) {
        $json = array(
            'type' => $r->resourceType(),
            'id'   => $r->resourceID(),
        );

        return array('resource', $json);
    }

    function visitBool($bool) { return $bool; }

    private function locationToJson(ValueExceptionCodeLocation $location = null) {
        if ($location === null)
            return null;

        return array(
            'file'       => $location->file(),
            'line'       => $location->line(),
            'sourceCode' => $location->sourceCode(),
        );
    }

    private function globalsToJson(ValueExceptionGlobalState $globals = null) {
        if ($globals === null)
            return null;

        $json = array(
            'staticProperties' => array(),
            'staticVariables'  => array(),
            'globalVariables'  => array(),
        );

        foreach ($globals->getStaticProperties() as $p) {
            $json['staticProperties'][] = array(
                'name'      => $p->name(),
                'value'     => $p->value()->acceptVisitor($this),
                'class'     => $p->className(),
                'access'    => $p->access(),
                'isDefault' => $p->isDefault(),
            );
        }

        foreach ($globals->getStaticVariables() as $v) {
            $json['staticVariables'][] = array(
                'name'     => $v->name(),
                'value'    => $v->value()->acceptVisitor($this),
                'function' => $v->getFunction(),
                'class'    => $v->getClass(),
            );
        }

        foreach ($globals->getGlobalVariables() as $v) {
            $json['globalVariables'][] = array(
                'name'  => $v->name(),
                'value' => $v->value()->acceptVisitor($this),
            );
        }

        return $json;
    }
}

final class JSONParse {
    /**
     * @param string $parse
     *
     * @return Value
     */
    static function fromJSON($parse) {
        $self       = new self;
        $self->root = JSON::parse($parse);

        return $self->parseValue($self->root['root']);
    }

    /** @var ValueObject[] */
    private $finishedObjects = array();
    /** @var ValueArray[] */
    private $finishedArrays = array();
    private $root;

    private function parseValue($v) {
        if (is_float($v)) {
            return new ValueFloat($v);
        } else if (is_int($v)) {
            return new ValueInt($v);
        } else if (is_bool($v)) {
            return new ValueBool($v);
        } else if (is_null($v)) {
            return new ValueNull;
        } else if (is_string($v)) {
            return new ValueString($v);
        } else {
            switch ($v[0]) {
                case 'object':
                    return $this->parseObject($v);
                case '-inf':
                case '+inf':
                case 'nan':
                case 'float':
                    return $this->parseFloat($v[1]);
                case 'array':
                    return $this->parseArray($v[1]);
                case 'exception':
                    return $this->parseException($v);
                case 'resource':
                    return $this->parseResource($v[1]);
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
    }

    private function parseException($x) {
        if ($x === null)
            return null;

        $e = $x[1];

        $result = new ValueException;
        $result->setClass($e['class']);
        $result->setCode($e['code']);
        $result->setMessage($e['message']);
        $result->setLocation($this->parseLocation($e['location']));
        $result->setPrevious($this->parseException($e['previous']));

        $stack = array();

        foreach ($e['stack'] as $s) {
            $frame = new ValueExceptionStackFrame;
            $frame->setObject($this->parseObject($s['object']));
            $frame->setLocation($this->parseLocation($s['location']));
            $frame->setClass($s['class']);
            $frame->setFunction($s['function']);
            $frame->setIsStatic($s['isStatic']);
            $args = array();

            if ($s['args'] === null) {
                $args = null;
            } else {
                foreach ($s['args'] as $arg) {
                    $args[] = $this->parseValue($arg);
                }
            }

            $frame->setArgs($args);
            $stack[] = $frame;
        }

        $result->setStack($stack);

        if ($e['locals'] !== null) {
            $locals = array();

            foreach ($e['locals'] as $local) {
                $var = new ValueVariable;
                $var->setName($local['name']);
                $var->setValue($this->parseValue($local['value']));
                $locals[] = $var;
            }

            $result->setLocals($locals);
        }

        if ($e['globals'] !== null) {
            $staticProperties = array();
            $staticVariables  = array();
            $globalVariables  = array();

            foreach ($e['globals']['staticProperties'] as $p) {
                $p2 = new ValueObjectPropertyStatic;
                $p2->setClass($p['class']);
                $p2->setName($p['name']);
                $p2->setAccess($p['access']);
                $p2->setValue($this->parseValue($p['value']));
                $p2->setIsDefault($p['isDefault']);
                $staticProperties[] = $p2;
            }

            foreach ($e['globals']['staticVariables'] as $v) {
                $v2 = new ValueVariableStatic;
                $v2->setClass($v['class']);
                $v2->setName($v['name']);
                $v2->setFunction($v['function']);
                $v2->setValue($this->parseValue($v['value']));
                $staticVariables[] = $v2;
            }

            foreach ($e['globals']['globalVariables'] as $v) {
                $v2 = new ValueGlobalVariable;
                $v2->setName($v['name']);
                $v2->setValue($this->parseValue($v['value']));
                $globalVariables[] = $v2;
            }

            $globals = new ValueExceptionGlobalState;
            $globals->setStaticProperties($staticProperties);
            $globals->setStaticVariables($staticVariables);
            $globals->setGlobalVariables($globalVariables);
            $result->setGlobals($globals);
        }

        return $result;
    }

    private function parseResource($x1) {
        return new ValueResource($x1['type'], $x1['id']);
    }

    private function parseArray($x) {
        $self =& $this->finishedArrays[$x];
        if ($self === null) {
            $x1   = $this->root['arrays'][$x];
            $self = new ValueArray;
            $self->setIsAssociative($x1['isAssociative']);
            $self->setID($x);

            foreach ($x1['entries'] as $e) {
                $self->addEntry($this->parseValue($e[0]),
                                $this->parseValue($e[1]));
            }
        }

        return $self;
    }

    private function parseObject($x) {
        if ($x === null)
            return null;

        $id   = $x[1];
        $self =& $this->finishedObjects[$id];

        if ($self === null) {
            $x1 = $this->root['objects'][$id];

            $self = new ValueObject;
            $self->setClass($x1['class']);
            $self->setHash($x1['hash']);
            $self->setId($id);

            foreach ($x1['properties'] as $p) {
                $p2 = new ValueObjectProperty;
                $p2->setName($p['name']);
                $p2->setValue($this->parseValue($p['value']));
                $p2->setClass($p['class']);
                $p2->setAccess($p['access']);
                $self->addProperty($p2);
            }
        }

        return $self;
    }

    private function parseFloat($x) {
        if ($x === '+inf')
            $result = INF;
        else if ($x === '-inf')
            $result = -INF;
        else if ($x === 'nan')
            $result = NAN;
        else
            $result = (float)$x;

        return new ValueFloat($result);
    }

    private function __construct() { }

    private function parseLocation($location) {
        if ($location === null)
            return null;

        $result = new ValueExceptionCodeLocation;
        $result->setFile($location['file']);
        $result->setLine($location['line']);
        $result->setSourceCode($location['sourceCode']);

        return $result;
    }
}

final class JSON {
    /**
     * @param mixed $value
     *
     * @return string
     */
    static function stringify($value) {
        $flags = 0;

        if (defined('JSON_PRETTY_PRINT')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $result = json_encode(self::prepare($value), $flags);

        self::checkError();

        return $result;
    }

    /**
     * @param string $json
     *
     * @return mixed
     */
    static function parse($json) {
        $result = json_decode($json, true);

        self::checkError();

        return self::unprepare($result);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     * @throws \Exception
     */
    private static function prepare($value) {
        if (is_string($value)) {
            return utf8_encode($value);
        }

        if (is_float($value) || is_int($value) || is_null($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            $result = array();

            foreach ($value as $k => $v) {
                $result[self::prepare($k)] = self::prepare($v);
            }

            return $result;
        }

        throw new \Exception("Invalid JSON value");
    }

    private static function checkError() {
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON Error", json_last_error());
        }
    }

    /**
     * @param mixed $result
     *
     * @throws \Exception
     * @return mixed
     */
    private static function unprepare($result) {
        if (is_string($result)) {
            return utf8_decode($result);
        }

        if (is_float($result) || is_int($result) || is_null($result) || is_bool($result)) {
            return $result;
        }

        if (is_array($result)) {
            $result2 = array();

            foreach ($result as $k => $v) {
                $result2[self::unprepare($k)] = self::unprepare($v);
            }

            return $result2;
        }

        throw new \Exception("Invalid JSON value");
    }
}

