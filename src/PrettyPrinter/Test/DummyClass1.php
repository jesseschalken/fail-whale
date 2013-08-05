<?php

namespace PrettyPrinter\Test;

class DummyClass1
{
	public static $publicStatic1;
	private static /** @noinspection PhpUnusedPrivateFieldInspection */
			$privateStatic1;
	protected static $protectedStatic1;
	public $public1;
	private /** @noinspection PhpUnusedPrivateFieldInspection */
			$private1;
	protected $protected1;
}