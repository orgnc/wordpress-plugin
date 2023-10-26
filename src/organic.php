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

// The DSN to use before we load client-specific DSNs.
const DEFAULT_SENTRY_DSN = 'https://e1cf660e5b3947a4bdf7c516afaaa7d2@o472819.ingest.sentry.io/4505048050434048';

define( 'Organic\ORGANIC_PLUGIN_VERSION', 'ORGANIC_PLUGIN_VERSION_VALUE' );

$environment = getenv( 'ORGANIC_ENVIRONMENT' ) ?: getenv( 'EMPIRE_ENVIRONMENT' );
if ( ! $environment ) {
    $environment = 'PRODUCTION';
}

function init_organic_sentry( string $dsn, string $environment ) : ?\Sentry\State\Hub {
    if ( ! in_array( $environment, [ 'PRODUCTION', 'STAGING' ] ) ) {
        return null;
    }
    if ( get_option( 'organic::log_to_sentry' ) === false ) {
        return null;
    }
    // Initialize a new Sentry Hub and Client to avoid interfering with
    // any publisher's pre-existing Sentry configuration.
    $options = [
        'dsn' => $dsn,
        'environment' => strtolower( $environment ),
    ];
    $client = \Sentry\ClientBuilder::create( $options )->getClient();
    return new \Sentry\State\Hub( $client );
}

$organic = new Organic( $environment, init_organic_sentry( DEFAULT_SENTRY_DSN, $environment ) );
$organic->init(
    getenv( 'ORGANIC_API_URL' ) ?: getenv( 'EMPIRE_API_URL' ),
    getenv( 'ORGANIC_CDN_URL' ) ?: getenv( 'EMPIRE_CDN_URL' )
);

$is_installed_as_mustuse_plugin = defined( 'WPMU_PLUGIN_DIR' )
    && strpos( __FILE__, WPMU_PLUGIN_DIR ) === 0;

$check_for_resync = function() use ( $organic, $is_installed_as_mustuse_plugin ) {
    $option = 'organic::resynced_on_version';
    $resynced_on_version = get_option( $option, '0.0.0' );
    // Version should be manually incremented when a sync is necessary post-upgrade
    if ( version_compare( '1.15.1', $resynced_on_version, 'gt' ) ) {
        $organic->triggerContentResync();
        update_option( $option, \Organic\ORGANIC_PLUGIN_VERSION, $is_installed_as_mustuse_plugin );
    }
};

if ( $is_installed_as_mustuse_plugin ) {
    // Activation hook is never called for must-use plugins. We need an additional solution.
    $check_for_resync();
} else {
    // On update or activation, we need to make sure content is re-synced because of changes to the plugin code
    register_activation_hook( __FILE__, $check_for_resync );
}

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
