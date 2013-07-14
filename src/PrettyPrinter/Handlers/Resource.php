<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\CachingHandler;
use PrettyPrinter\Text;

final class Resource extends CachingHandler
{
	private $resourceIds = array();

	function cacheMiss( $resource )
	{
		$id =& $this->resourceIds[ "$resource" ];

		if ( !isset( $id ) )
			$id = $this->newId();

		return Text::line( get_resource_type( $resource ) . " $id" );
	}
}