<?php
namespace PrettyPrinter\Handlers;

use PrettyPrinter\Handler;
use PrettyPrinter\Table;
use PrettyPrinter\Text;

final class Null extends Handler
{
	function handleValue( &$null )
	{
		return Text::line( 'null' );
	}

	protected function prettyPrintVariables( array $variables )
	{
		if ( empty( $variables ) )
			return Text::line( 'none' );

		$table = new Table;

		foreach ( $variables as $k => &$v )
		{
			$row = $table->newRow();
			$row->addCell( $this->prettyPrintVariable( $k ) );
			$row->addTextCell( ' = ' );
			$row->addCell( $this->prettyPrintRef( $v )->append( ';' ) );
		}

		return $table->render();
	}
}