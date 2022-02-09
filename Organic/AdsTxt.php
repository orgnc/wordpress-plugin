<?php

namespace Organic;

/**
 * Manages the ads.txt data and presentation
 *
 * @package Organic
 */
class AdsTxt {


    /**
     * @var Organic
     */
    private $organic;

    public function __construct(Organic $organic ) {
        $this->organic = $organic;

        add_action( 'init', array( $this, 'show' ) );
    }

    public function get() {
         return $this->organic->getOption( 'organic::ads_txt' );
    }

    public function show() {
        if ( isset( $_SERVER ) && $_SERVER['REQUEST_URI'] === '/ads.txt' ) {
            $enabled = $this->organic->getOption( 'organic::enabled' );

            if ( $enabled ) {
                $adsTxt = $this->organic->getOption( 'organic::ads_txt' );
                header( 'content-type: text/plain; charset=UTF-8' );
                header( 'cache-control: public, max-age=86400' );
                echo $adsTxt;
                exit;
            }
        }
    }

    public function update( string $content ) {
        $this->organic->updateOption( 'organic::ads_txt', $content );
    }
}
