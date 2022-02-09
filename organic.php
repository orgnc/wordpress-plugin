<?php

/**
 * Plugin Name: Organic
 * Plugin URI: http://github.com/orgnc/wordpress-plugin
 * Description: Ads, Analytics & Affiliate Management
 * Version: VERSION
 * Author: Organic Ventures Inc
 * Author URI: https://organic.ly
 */
require __DIR__ . '/vendor/autoload.php';

use Organic\Organic;

$environment = getenv( 'ORGANIC_ENVIRONMENT' );
if ( ! $environment ) {
    /* @deprecated */
    $environment = getenv( 'EMPIRE_ENVIRONMENT' );

    if ( ! $environment ) {
        $environment = 'PRODUCTION';
    }
}

$organic = new Organic( $environment );
$organic->init( getenv( 'EMPIRE_API_URL', getenv( 'EMPIRE_CDN_URL' ) ) );
