<?php

namespace FailWhale;

class RefCounts {
    public  $strings = array();
    public  $arrays  = array();
    public  $objects = array();
    private $root;

    public function __construct(Data\Root $root) {
        $this->root = $root;
        $this->doValue($root->root);
    }

    private function doValue(Data\Value_ $value) {
        switch ($value->type) {
            case Data\Type::ARRAY1:
                $this->doArray($value->array);
                break;
            case Data\Type::OBJECT:
                $this->doObject($value->object);
                break;
            case Data\Type::STRING:
                $refCount =& $this->strings[$value->string];
                $refCount++;
                break;
            case Data\Type::EXCEPTION:
                $this->doException($value->exception);
        }
    }

    private function doArray($id) {
        $refCount =& $this->arrays[$id];
        $refCount++;

        if ($refCount == 1) {
            $array = $this->root->arrays[$id];

            foreach ($array->entries as $entry) {
                $this->doValue($entry->key);
                $this->doValue($entry->value);
            }
        }
    }

    private function doObject($id) {
        $refCount =& $this->objects[$id];
        $refCount++;

        if ($refCount == 1) {
            $object = $this->root->objects[$id];

            foreach ($object->properties as $property) {
                $this->doValue($property->value);
            }
        }
    }

    private function doException(Data\Exception_ $e) {
        if ($e->globals) {
            foreach ($e->globals->staticVariables as $var) {
                $this->doValue($var->value);
            }
            foreach ($e->globals->staticProperties as $var) {
                $this->doValue($var->value);
            }
            foreach ($e->globals->globalVariables as $var) {
                $this->doValue($var->value);
            }
        }

        foreach ($e->exceptions as $e2) {
            if ($e2->stack) {
                foreach ($e2->stack as $stack) {
                    if ($stack->object) {
                        $this->doObject($stack->object);
                    }

                    if ($stack->args) {
                        foreach ($stack->args as $arg) {
                            $this->doValue($arg->value);
                        }
                    }

                    if ($stack->locals) {
                        foreach ($stack->locals as $local) {
                            $this->doValue($local->value);
                        }
                    }
                }
            }
        }
    }
}
