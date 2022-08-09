<?php

namespace Organic;

/**
 * Manages the ads.txt data and presentation
 *
 * @package Organic
 */
class AdsTxt {

    const ADS_TXT_URL_TEMPLATE = 'https://api.organiccdn.io/sdk/adstxt/%s';

    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;

        add_action( 'init', array( $this, 'show' ) );
    }

    public function get() {
        return sprintf( self::ADS_TXT_URL_TEMPLATE, $this->organic->getSiteId() );
    }

    public function show() {
        if ( isset( $_SERVER ) && $_SERVER['REQUEST_URI'] === '/ads.txt' ) {
            $enabled = $this->organic->getOption( 'organic::enabled' );

            if ( $enabled ) {
                header( 'Location: ' . $this->get() );
                exit;
            }
        }
    }
}

