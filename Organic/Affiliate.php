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
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_post_page_enqueue' ]);
    }

    public function register_scripts() {
        $siteId = $this->organic->getSiteId();
        $sdk_url = $this->organic->sdk->getSdkV2Url();
        wp_enqueue_script( 'organic-sdk', $sdk_url, [], $this->organic->version );
        wp_register_script(
            'organic-affiliate-product-card',
            plugins_url( 'affiliate/blocks/productCard/build/index.js', __DIR__ ),
            [ 'organic-sdk' ],
            $this->organic->version
        );
        wp_register_script(
            'organic-affiliate-product-carousel',
            plugins_url( 'affiliate/blocks/productCarousel/build/index.js', __DIR__ ),
            [ 'organic-sdk' ],
            $this->organic->version
        );
        $product_search_page_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-search';
        $product_card_creation_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-card';
        $product_carousel_creation_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-carousel';
        wp_localize_script(
            'organic-affiliate-product-card',
            'organic_affiliate_config_product_card',
            [
                'productSearchPageUrl' => $product_search_page_url . '?siteGuid=' . $siteId,
                'productCardCreationURL' => $product_card_creation_url . '?siteGuid=' . $siteId,
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
            plugin_dir_path( __DIR__ ) . 'affiliate/blocks/productCard'
        );
        register_block_type(
            plugin_dir_path( __DIR__ ) . 'affiliate/blocks/productCarousel'
        );
    }

    function admin_post_page_enqueue($hook_suffix) {
        // Scripts to enqueue only for WP Post pages.
        if( 'post.php' != $hook_suffix && 'post-new.php' != $hook_suffix ) {
            return;
        }
        wp_enqueue_script(
            'on-post-load-scripts',
            plugins_url('affiliate/initSDKOnPostLoad.js', __DIR__),
            [ 'organic-sdk' ],
            $this->organic->version
        );
    }
}
