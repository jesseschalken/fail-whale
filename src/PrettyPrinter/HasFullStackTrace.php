<?php

namespace PrettyPrinter;

interface HasFullStackTrace
{
	/**
	 * @return array
	 */
	function getFullStackTrace();
}