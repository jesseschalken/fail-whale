<?php

namespace PrettyPrinter\Reflection
{
	use PrettyPrinter\Utils\Text;

	class GlobalVariable extends Variable
	{
		function isSuperGlobal()
		{
			return in_array( $this->name(), array( '_POST',
			                                       '_GET',
			                                       '_SESSION',
			                                       '_COOKIE',
			                                       '_FILES',
			                                       '_REQUEST',
			                                       '_ENV',
			                                       '_SERVER' ), true );
		}

		function prefix()
		{
			return new Text( $this->isSuperGlobal() ? '' : 'global ' );
		}
	}
}