<?php

namespace PrettyPrinter;

interface HasStackTraceWithCurrentObjects
{
	/**
	 * @return array
	 */
	function getStackTraceWithCurrentObjects();
}