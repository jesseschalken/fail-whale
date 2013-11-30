<?php

namespace PrettyPrinter
{
	class Setting
	{
		private $pp;

		function __construct( PrettyPrinter $pp )
		{
			$this->pp = $pp;
		}

		protected function pp() { return $this->pp; }
	}
}

namespace PrettyPrinter\Settings
{
	use PrettyPrinter\PrettyPrinter;
	use PrettyPrinter\Setting;

	class Bool extends Setting
	{
		private $value;

		/**
		 * @param PrettyPrinter $pp
		 * @param bool          $value
		 */
		function __construct( PrettyPrinter $pp, $value )
		{
			parent::__construct( $pp );

			$this->value = $value;
		}

		function set( $v )
		{
			$this->value = (bool) $v;

			return $this->pp();
		}

		function yes() { return $this->set( true ); }

		function no() { return $this->set( false ); }

		function get() { return $this->value; }
	}

	class Number extends Setting
	{
		private $value;

		/**
		 * @param PrettyPrinter $pp
		 * @param int           $value
		 */
		function __construct( PrettyPrinter $pp, $value )
		{
			parent::__construct( $pp );

			$this->value = $value;
		}

		function set( $v )
		{
			$this->value = (int) $v;

			return $this->pp();
		}

		function get() { return $this->value; }
	}
}

