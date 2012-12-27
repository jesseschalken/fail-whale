<?php

final class StringPrettyPrinter extends CachingPrettyPrinter
{
	private $characterEscapeCache = array();

	public function __construct( ValuePrettyPrinter $valuePrettyPrinter )
	{
		parent::__construct( $valuePrettyPrinter );

		$tab                        = $this->settings()->escapeTabs()->isYes() ? '\t' : "\t";
		$newLine                    = $this->settings()->splitMultiLineStrings()->isYes() ? "\\n\" .\n\"" : '\n';
		$this->characterEscapeCache = array( "\\" => '\\\\',
		                                     "\$" => '\$',
		                                     "\r" => '\r',
		                                     "\v" => '\v',
		                                     "\t" => $tab,
		                                     "\n" => $newLine,
		                                     "\f" => '\f',
		                                     "\"" => '\"' );
	}

	protected function cacheMiss( $string )
	{
		$escaped   = '';
		$length    = strlen( $string );
		$maxLength = $this->settings()->maxStringLength()->get();

		for ( $i = 0; $i < $length && $i < $maxLength; $i++ ) {
			$char        = $string[$i];
			$charEscaped =& $this->characterEscapeCache[$char];

			if ( !isset( $charEscaped ) ) {
				$ord         = ord( $char );
				$charEscaped = $ord >= 32 && $ord <= 126 ? $char : '\x' . substr( '00' . dechex( $ord ), -2 );
			}

			$escaped .= $charEscaped;
		}

		return PrettyPrinterLines::split( "\"$escaped" . ( $i === $length ? "\"" : "..." ) );
	}
}

