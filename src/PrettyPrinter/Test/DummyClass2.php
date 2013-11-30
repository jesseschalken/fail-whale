<?php

namespace PrettyPrinter\Test
{
	class DummyClass2 extends DummyClass1
	{
		public static $publicStatic2;
		private static /** @noinspection PhpUnusedPrivateFieldInspection */
				$privateStatic2;
		protected static $protectedStatic2;
		public $public2;
		private /** @noinspection PhpUnusedPrivateFieldInspection */
				$private2;
		protected $protected2;
	}
}