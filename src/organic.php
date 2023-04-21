<?php

/**
 * Plugin Name: Organic
 * Plugin URI: http://github.com/orgnc/wordpress-plugin
 * Description: Ads, Analytics & Affiliate Management
 * Version: ORGANIC_PLUGIN_VERSION_VALUE
 * Author: Organic Ventures Inc
 * Author URI: https://organic.ly
 */
require __DIR__ . '/vendor/autoload.php';

use Organic\Organic;
use const Organic\DEFAULT_SENTRY_DSN;

define( 'Organic\ORGANIC_PLUGIN_VERSION', 'ORGANIC_PLUGIN_VERSION_VALUE' );

$environment = getenv( 'ORGANIC_ENVIRONMENT' ) ?: getenv( 'EMPIRE_ENVIRONMENT' );
if ( ! $environment ) {
    $environment = 'PRODUCTION';
}

function init_sentry( string $dsn, string $environment ) {
    if ( $environment != 'PRODUCTION' ) {
        return;
    }
    \Sentry\init(
        [
            'dsn' => $dsn,
            'environment' => strtolower( $environment ),
        ]
    );
}

init_sentry( DEFAULT_SENTRY_DSN, $environment );

$organic = new Organic( $environment );
$organic->init(
    getenv( 'ORGANIC_API_URL' ) ?: getenv( 'EMPIRE_API_URL' ),
    getenv( 'ORGANIC_CDN_URL' ) ?: getenv( 'EMPIRE_CDN_URL' )
);

function add_organic_block_category( $categories ) {
    return array_merge(
        $categories,
        [
            [
                'slug' => 'organic-blocks',
                'title' => 'Organic',
            ],
        ]
    );
}

add_action( 'block_categories_all', 'add_organic_block_category', PHP_INT_MAX - 1 );
