<?php

function define_if_not_defined( $constant, $value )
{
	if ( !defined( $constant ) )
		define( $constant, $value );
}

function define_php_version_constants()
{
	list( $major, $minor, $release ) = explode( '.', PHP_VERSION );

	define_if_not_defined( 'PHP_MAJOR_VERSION', (int) $major );
	define_if_not_defined( 'PHP_MINOR_VERSION', (int) $minor );
	define_if_not_defined( 'PHP_RELEASE_VERSION', (int) $release );
	define_if_not_defined( 'PHP_EXTRA_VERSION', substr( $release, strlen( (int) $release ) ) );
	define_if_not_defined( 'PHP_VERSION_ID', ( $major * 100 + $minor ) * 100 + $release );
}

define_php_version_constants();

define_if_not_defined( 'E_RECOVERABLE_ERROR', 4096 );
define_if_not_defined( 'E_DEPRECATED', 8192 );
define_if_not_defined( 'E_USER_DEPRECATED', 16384 );
