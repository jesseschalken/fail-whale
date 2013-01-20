<?php

final class StringPrettyPrinter extends CachingPrettyPrinter
{
	private $characterEscapeCache = array( "\\" => '\\\\',
	                                       "\$" => '\$',
	                                       "\r" => '\r',
	                                       "\v" => '\v',
	                                       "\f" => '\f',
	                                       "\"" => '\"' );

	function __construct( ValuePrettyPrinter $valuePrettyPrinter )
	{
		parent::__construct( $valuePrettyPrinter );

		$settings = $this->settings();

		$this->characterEscapeCache[ "\t" ] = $settings->escapeTabsInStrings()->ifElse( '\t', "\t" );
		$this->characterEscapeCache[ "\n" ] = $settings->splitMultiLineStrings()->ifElse( "\\n\" .\n\"", '\n' );
	}

	protected function cacheMiss( $string )
	{
		$escaped   = '';
		$length    = strlen( $string );
		$maxLength = $this->settings()->maxStringLength()->get();

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

		return PrettyPrinterLines::split( "\"$escaped" . ( $i === $length ? "\"" : "..." ) );
	}
}

