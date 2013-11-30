<?php

namespace PrettyPrinter\Utils
{
	class ArrayUtil
	{
		static function get( $array, $key, $default = null )
		{
			return isset( $array[ $key ] ) ? $array[ $key ] : $default;
		}

		static function isAssoc( array $array )
		{
			$i = 0;

			/** @noinspection PhpUnusedLocalVariableInspection */
			foreach ( $array as $k => &$v )
				if ( $k !== $i++ )
					return true;

			return false;
		}

		static function lastKey( array $array )
		{
			/** @noinspection PhpUnusedLocalVariableInspection */
			foreach ( $array as $k => &$v )
				;

			return isset( $k ) ? $k : null;
		}
	}
}

namespace PrettyPrinter\Utils
{
	class Ref
	{
		static function get( &$ref )
		{
			return $ref;
		}

		static function set( &$ref, $value = null )
		{
			$ref = $value;
		}

		static function &create( $value = null )
		{
			return $value;
		}

		static function equal( &$a, &$b )
		{
			$aOld   = $a;
			$a      = new \stdClass;
			$result = $a === $b;
			$a      = $aOld;

			return $result;
		}
	}
}

namespace PrettyPrinter\Utils
{
	class Table implements \Countable
	{
		/** @var (Text[])[] */
		private $rows = array();

		function __clone()
		{
			foreach ( $this->rows as &$row )
				foreach ( $row as &$cell )
					$cell = clone $cell;
		}

		function render()
		{
			$columnWidths = array();
			$result       = new Text;

			/** @var $cell Text */
			foreach ( $this->rows as $cells )
			{
				foreach ( $cells as $column => $cell )
				{
					$width =& $columnWidths[ $column ];
					$width = max( (int) $width, $cell->width() );
				}
			}

			foreach ( $this->rows as $cells )
			{
				$row        = new Text;
				$lastColumn = ArrayUtil::lastKey( $cells );

				foreach ( $cells as $column => $cell )
				{
					if ( $column !== $lastColumn )
						$cell->padWidth( $columnWidths[ $column ] );

					$row->appendLines( $cell );
				}

				$result->addLines( $row );
			}

			return $result;
		}

		function count()
		{
			return count( $this->rows );
		}

		/**
		 * @param Text[] $cells
		 *
		 * @return self
		 */
		function addRow( array $cells )
		{
			foreach ( $cells as &$cell )
				$cell = clone $cell;

			$this->rows[ ] = $cells;

			return $this;
		}
	}
}

namespace PrettyPrinter\Utils
{
	class Text
	{
		static function create( $string = "" )
		{
			return new self( $string );
		}

		private $lines, $hasEndingNewLine, $newLineChar;

		function __construct( $text = "", $newLineChar = "\n" )
		{
			$this->newLineChar = $newLineChar;
			$this->lines       = explode( $this->newLineChar, $text );

			if ( $this->hasEndingNewLine = $this->lines[ count( $this->lines ) - 1 ] === "" )
				array_pop( $this->lines );
		}

		function __toString()
		{
			$text = join( $this->newLineChar, $this->lines );

			if ( $this->hasEndingNewLine && !empty( $this->lines ) )
				$text .= $this->newLineChar;

			return $text;
		}

		/**
		 * @param Text $add
		 *
		 * @return self
		 */
		function addLines( self $add )
		{
			foreach ( $add->lines as $line )
				$this->lines[ ] = $line;

			return $this;
		}

		function swapLines( self $other )
		{
			$clone       = clone $this;
			$this->lines = $other->lines;

			return $clone;
		}

		/**
		 * @param Text $append
		 *
		 * @return self
		 */
		function appendLines( self $append )
		{
			$space = str_repeat( ' ', $this->width() );
			$lines =& $this->lines;

			foreach ( $append->lines as $k => $line )
				if ( $k === 0 && !empty( $lines ) )
					$lines[ count( $lines ) - 1 ] .= $line;
				else
					$lines[ ] = $space . $line;

			return $this;
		}

		function width()
		{
			$lines = $this->lines;

			return empty( $lines ) ? 0 : strlen( $lines[ count( $lines ) - 1 ] );
		}

		/**
		 * @param int $times
		 *
		 * @return self
		 */
		function indent( $times = 1 )
		{
			$space = str_repeat( '  ', $times );

			foreach ( $this->lines as $k => $line )
				if ( $line !== '' )
					$this->lines[ $k ] = $space . $line;

			return $this;
		}

		function addLinesBefore( self $addBefore )
		{
			return $this->addLines( $this->swapLines( $addBefore ) );
		}

		function wrap( $prepend, $append )
		{
			return $this->prepend( $prepend )->append( $append );
		}

		function wrapLines( $prepend = '', $append = '' )
		{
			return $this->prependLine( $prepend )->addLine( $append );
		}

		/**
		 * @param string $line
		 *
		 * @return self
		 */
		function addLine( $line = "" )
		{
			return $this->addLines( new self( $line . $this->newLineChar ) );
		}

		function append( $string )
		{
			return $this->appendLines( new self( $string ) );
		}

		/**
		 * @param $string
		 *
		 * @return self
		 */
		function prepend( $string )
		{
			return $this->prependLines( new self( $string ) );
		}

		function prependLine( $line = "" )
		{
			return $this->addLines( $this->swapLines( new self( $line . $this->newLineChar ) ) );
		}

		function prependLines( self $lines )
		{
			return $this->appendLines( $this->swapLines( $lines ) );
		}

		function padWidth( $width )
		{
			return $this->append( str_repeat( ' ', $width - $this->width() ) );
		}

		function setHasEndingNewline( $value )
		{
			$this->hasEndingNewLine = (bool) $value;

			return $this;
		}

		function count()
		{
			return count( $this->lines );
		}
	}
}

