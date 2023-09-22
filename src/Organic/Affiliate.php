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
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ] );
        }
    }

    public function register_scripts( $hook_suffix ) {

        if ( ! function_exists( 'register_block_type' ) ) {
            // Gutenberg blocks are not supported
            return;
        }
        // As more blocks are added, create a function like "register_widget_block_script".
        $card_asset_file = include( plugin_dir_path( __DIR__ ) . 'blocks/affiliate/productCard/build/index.asset.php' );
        wp_register_script(
            'organic-affiliate-product-card',
            plugins_url( 'blocks/affiliate/productCard/build/index.js', __DIR__ ),
            $card_asset_file['dependencies'],
            $this->organic->version
        );
        wp_enqueue_style(
            'organic-affiliate-product-card-css-1',
            plugins_url( 'blocks/affiliate/productCard/build/index.css', __DIR__ ),
            [],
            $this->organic->version
        );
        wp_enqueue_style(
            'organic-affiliate-product-card-css-2',
            plugins_url( 'blocks/affiliate/productCard/build/style-index.css', __DIR__ ),
            [],
            $this->organic->version
        );

        $carousel_asset_file = include( plugin_dir_path( __DIR__ ) . 'blocks/affiliate/productCarousel/build/index.asset.php' );
        wp_register_script(
            'organic-affiliate-product-carousel',
            plugins_url( 'blocks/affiliate/productCarousel/build/index.js', __DIR__ ),
            $carousel_asset_file['dependencies'],
            $this->organic->version
        );
        wp_enqueue_style(
            'organic-affiliate-product-carousel-css-1',
            plugins_url( 'blocks/affiliate/productCarousel/build/index.css', __DIR__ ),
            [],
            $this->organic->version
        );
        wp_enqueue_style(
            'organic-affiliate-product-carousel-css-2',
            plugins_url( 'blocks/affiliate/productCarousel/build/style-index.css', __DIR__ ),
            [],
            $this->organic->version
        );
        $product_search_page_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-search';
        $product_card_creation_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-card';
        $product_carousel_creation_url = $this->organic->getPlatformUrl() . '/apps/affiliate/integrations/product-carousel';
        $siteId = $this->organic->getSiteId();
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
        if ( ! function_exists( 'register_block_type' ) ) {
            // Gutenberg blocks are not supported
            return;
        }

        register_block_type(
            plugin_dir_path( __DIR__ ) . 'blocks/affiliate/productCard'
        );
        register_block_type(
            plugin_dir_path( __DIR__ ) . 'blocks/affiliate/productCarousel'
        );
    }
}
