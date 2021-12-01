<?php

/**
 * Plugin Name: Empire
 * Plugin URI: http://github.com/empireio/wordpress-plugin
 * Description: Ads, Analytics & Affiliate Management
 * Version: VERSION
 * Author: Empire Software Holdings Inc
 * Author URI: https://empireio.com
 */
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/Empire/helpers.php';
require __DIR__ . '/Empire/public.php';

use Empire\Empire;

$environment = getenv( 'EMPIRE_ENVIRONMENT' );
if ( ! $environment ) {
    $environment = 'PRODUCTION';
}

$empire = new Empire( $environment );
$empire->init( getenv( 'EMPIRE_API_URL' ) );
