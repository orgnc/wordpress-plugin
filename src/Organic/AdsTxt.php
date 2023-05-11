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

        add_action( 'init', [ $this, 'show' ] );
    }

    public function get() {
        return $this->organic->getOption( 'organic::ads_txt' );
    }

    public function getAdsTxtUrl() {
        return sprintf( self::ADS_TXT_URL_TEMPLATE, $this->organic->getSiteId() );
    }

    public function enableAdsTxtRedirect( $enable = false ) {
        $this->organic->updateOption( 'organic::ads_txt_redirect_enabled', $enable );
    }

    public function show() {
        if ( isset( $_SERVER ) && $_SERVER['REQUEST_URI'] === '/ads.txt' ) {
            $enabled = $this->organic->getOption( 'organic::enabled' );

            if ( $enabled ) {
                $adsTxtRedirect = $this->organic->adsTxtRedirectionEnabled();
                if ( $adsTxtRedirect ) {
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
                $adsTxt = $this->organic->getOption( 'organic::ads_txt' );
                header( 'content-type: text/plain; charset=UTF-8' );
                header( 'cache-control: public, max-age=86400' );
                if ( defined( 'WP_ENV' ) && WP_ENV !== 'production' ) {
                    header( 'x-robots-tag: noindex, nofollow' );
                }
                echo esc_html( $adsTxt );
                exit;
            }
        }
    }

    public function update( string $content ) {
        $this->organic->updateOption( 'organic::ads_txt', $content );
    }

}

