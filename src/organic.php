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
use Organic\NullErrorReporter;
use Organic\SentryHttpErrorReporter;

// The DSN to use before we load client-specific DSNs.
const DEFAULT_SENTRY_DSN = 'https://e1cf660e5b3947a4bdf7c516afaaa7d2@o472819.ingest.sentry.io/4505048050434048';

define( 'Organic\ORGANIC_PLUGIN_VERSION', 'ORGANIC_PLUGIN_VERSION_VALUE' );

$environment = getenv( 'ORGANIC_ENVIRONMENT' ) ?: getenv( 'EMPIRE_ENVIRONMENT' );
if ( ! $environment ) {
    $environment = 'PRODUCTION';
}

function init_organic_error_reporter( string $dsn, string $environment ) : \Organic\ErrorReporter {
    if ( ! in_array( $environment, [ 'PRODUCTION', 'STAGING' ] ) ) {
        return new NullErrorReporter();
    }
    if ( function_exists( 'get_option' ) && get_option( 'organic::log_to_sentry' ) === false ) {
        return new NullErrorReporter();
    }
    return new SentryHttpErrorReporter( $dsn, $environment );
}

$organic = new Organic( $environment, init_organic_error_reporter( DEFAULT_SENTRY_DSN, $environment ) );
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
