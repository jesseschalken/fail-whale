<?php

namespace PrettyPrinter\Handlers;

use PrettyPrinter\CachingHandler;
use PrettyPrinter\Handlers\Any;
use PrettyPrinter\Text;

final class String extends CachingHandler
{
	private $characterEscapeCache = array( "\\" => '\\\\',
	                                       "\$" => '\$',
	                                       "\r" => '\r',
	                                       "\v" => '\v',
	                                       "\f" => '\f',
	                                       "\"" => '\"' );

	function __construct( Any $valueHandler )
	{
		parent::__construct( $valueHandler );

		$settings = $this->settings();

		$this->characterEscapeCache[ "\t" ] = $settings->escapeTabsInStrings ? '\t' : "\t";
		$this->characterEscapeCache[ "\n" ] = $settings->splitMultiLineStrings ? "\\n\" .\n\"" : '\n';
	}

	protected function handleCacheMiss( $string )
	{
		$escaped   = '';
		$length    = strlen( $string );
		$maxLength = $this->settings()->maxStringLength;

		for ( $i = 0; $i < $length && $i < $maxLength; $i++ )
		{
			$char        = $string[ $i ];
			$charEscaped =& $this->characterEscapeCache[ $char ];

			if ( !isset( $charEscaped ) )
			{
				$ord         = ord( $char );
				$charEscaped = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr( '00' . dechex( $ord ), -2 );
			}

			$escaped .= $charEscaped;
		}

		return Text::split( "\"$escaped" . ( $i === $length ? "\"" : "..." ) );
	}
}

