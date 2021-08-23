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
require 'Empire/helpers.php';

use Empire\Empire;

$environment = getenv('EMPIRE_ENVIRONMENT');
if ( !$environment ) {
    $environment = 'PRODUCTION';
}

$empire = new Empire($environment);
