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

    public function getAdsTxtUrl() {
        return sprintf( self::ADS_TXT_URL_TEMPLATE, $this->organic->getSiteId() );
    }

    public function show() {
        if ( isset( $_SERVER ) && $_SERVER['REQUEST_URI'] === '/ads.txt' ) {
            $enabled = $this->organic->getOption( 'organic::enabled' );

            if ( $enabled ) {
                /*
                 * Only one redirect is allowed for /ads.txt per Ads.txt specification:
                 *
                 * Only a single HTTP redirect to a destination outside the original
                 * root domain is allowed to facilitate one-hop delegation of
                 * authority to a third party's web server domain.
                 */
                header( 'Location: ' . $this->getAdsTxtUrl() );
                exit;
            }
        }
    }
}

