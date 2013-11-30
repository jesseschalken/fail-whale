<?php

namespace PrettyPrinter\TypeHandlers
{
	use PrettyPrinter\CachingTypeHandler;
	use PrettyPrinter\Utils\Text;

	final class Resource extends CachingTypeHandler
	{
		private $resourceIds = array();

		protected function handleCacheMiss( $resource )
		{
			$id =& $this->resourceIds[ "$resource" ];

			if ( !isset( $id ) )
				$id = $this->newId();

			return new Text( get_resource_type( $resource ) . " $id" );
		}
	}
}