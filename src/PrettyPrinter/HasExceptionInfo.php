<?php

namespace PrettyPrinter;

interface HasExceptionInfo
{
	/**
	 * @return ExceptionInfo
	 */
	function info();
}