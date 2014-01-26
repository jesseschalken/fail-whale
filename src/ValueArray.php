<?php

namespace ErrorHandler;

interface ValueArray {
    /** @return bool */
    function isAssociative();

    /** @return int */
    function id();

    /** @return ValueArrayEntry[] */
    function entries();
}

interface ValueArrayEntry {
    /** @return Value */
    function key();

    /** @return Value */
    function value();
}

