<?php

namespace PrettyPrinter
{
    interface HasFullTrace
    {
        /**
         * @return array
         */
        function getFullTrace();
    }

    interface HasLocalVariables
    {
        /**
         * @return array|null
         */
        function getLocalVariables();
    }
}
