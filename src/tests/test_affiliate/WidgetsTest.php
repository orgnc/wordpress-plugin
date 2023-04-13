<?php

namespace Organic;

use Exception;
use PHPUnit\Framework\TestCase;

include( __DIR__ . '/../SeleniumBrowser.php' );

define("Organic\WP_VERSION", getenv('WP_VERSION') ?? '');

class WidgetsTest extends TestCase {

    /**
     * Tests for custom widgets.
     * Note that these tests will fail for non-Gutenberg WordPress (< version 5).
     */

    function wordPressVersionTooLow() : bool {
        if ( !empty( WP_VERSION ) && intval( substr( WP_VERSION, 0, 1 ) ) < 5 ) {
            return true;
        }
        return false;
    }

    function checkWidgetIsAvailable( $block_type ) {
        if ( $this->wordPressVersionTooLow() ) {
            $this->fail('WordPress version ' . WP_VERSION . ' does not support custom blocks.');
        }
        fwrite(STDERR, print_r('\n Getting Selenium Browser', TRUE));
        $browser = SeleniumBrowser::get_test_browser();
        try {
            fwrite(STDERR, print_r('\n Going to page', TRUE));
            $browser->go_to_new_post();
            $browser->add_block( $block_type );
            $browser->wait( .5 );
            $url_correct = str_contains( $browser->get_iframe_url( 0 ), 'app.organic.ly');
            $browser->quit();
            $this->assertTrue( $url_correct );
        } catch (Exception $e) {
            fwrite(STDERR, print_r($e->getMessage(), TRUE));
            $browser->quit();
            $this->fail();
        }
    }

    /**
     * Test that the product card block is available.
     */
    public function testProductCard() {
        $this->checkWidgetIsAvailable( 'organic-affiliate-product-card' );
    }

    /**
     * Test that the product carousel block is available.
     */
    public function testProductCarousel() {
        $this->checkWidgetIsAvailable( 'organic-affiliate-product-carousel' );
    }



}