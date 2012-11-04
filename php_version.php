<?php

if ( !defined( 'PHP_VERSION_ID' ) ) {
  $version = explode( '.', PHP_VERSION );

  define( 'PHP_MAJOR_VERSION'   , (int) $version[0] );
  define( 'PHP_MINOR_VERSION'   , (int) $version[1] );
  define( 'PHP_RELEASE_VERSION' , (int) $version[2] );
  define( 'PHP_EXTRA_VERSION'   , substr( $version[2], strlen( (string) PHP_RELEASE_VERSION ) ) );
  define( 'PHP_VERSION_ID'      , PHP_MAJOR_VERSION * 10000 + PHP_MINOR_VERSION * 100 + PHP_RELEASE_VERSION );
}

