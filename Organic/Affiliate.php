<?php

namespace Organic;

class Affiliate {
    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        add_action( 'init', [ $this, 'register_gutenberg_block' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ] );
    }

    public function register_scripts() {
        $siteId = $this->organic->getSiteId();
        $sdk_url = $this->organic->sdk->getSdkV2Url();
        wp_enqueue_script( 'organic-sdk', $sdk_url, [], null );
        wp_register_script(
            'organic-affiliate',
            plugins_url( 'affiliate/build/index.js', __DIR__ ),
            [ 'organic-sdk' ]
        );
        $product_search_page_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-search';
        $product_carousel_creation_url = $this->organic->getPlatformUrl() . 'apps/affiliate/integrations/product-carousel';
        wp_localize_script(
            'organic-affiliate',
            'organic_affiliate_config',
            [
                'productSearchPageUrl' => $product_search_page_url . '?siteGuid=' . $siteId,
                'productCarouselCreationURL' => $product_carousel_creation_url . '?siteGuid=' . $siteId,
            ]
        );
    }

    public function register_gutenberg_block() {
        register_block_type(
            plugin_dir_path( __DIR__ ) . 'affiliate/build/productCard'
        );
        register_block_type(
            plugin_dir_path( __DIR__ ) . 'affiliate/build/productCarousel'
        );
    }
}
