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
            'organic-affiliate-product-card',
            plugins_url( 'affiliate/build/productCard/index.js', __DIR__ ),
            [ 'organic-sdk' ]
        );
        wp_register_script(
            'organic-affiliate-product-carousel',
            plugins_url( 'affiliate/build/productCarousel/index.js', __DIR__ ),
            [ 'organic-sdk' ]
        );
        $product_search_page_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-search';
        $product_carousel_creation_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-carousel';
        wp_localize_script(
            'organic-affiliate-product-card',
            'organic_affiliate_config_product_card',
            [
                'productSearchPageUrl' => $product_search_page_url . '?siteGuid=' . $siteId,
            ]
        );
        wp_localize_script(
            'organic-affiliate-product-carousel',
            'organic_affiliate_config_product_carousel',
            [
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
