<?php
namespace PrettyPrinter;

use PrettyPrinter\Handler;

abstract class CachingHandler extends Handler
{
	private $cache = array();

	final function handleValue( &$value )
	{
		$result =& $this->cache[ "$value" ];

		if ( $result === null )
			$result = $this->cacheMiss( $value );

		return clone $result;
	}

	/**
	 * @param $value
	 *
	 * @return Text
	 */
	protected abstract function cacheMiss( $value );

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