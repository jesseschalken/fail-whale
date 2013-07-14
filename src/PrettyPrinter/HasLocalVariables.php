<?php

namespace PrettyPrinter;

interface HasLocalVariables
{
	/**
	 * @return array
	 */
	function getLocalVariables();
}