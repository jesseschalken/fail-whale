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

