<?php

namespace FailWhale;

function set_exception_trace(\Exception $e, array $trace) {
    $prop = new \ReflectionProperty('Exception', 'trace');
    $prop->setAccessible(true);
    $prop->setValue($e, $trace);
}
