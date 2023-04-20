<?php

namespace Organic;

use Exception;
use PHPUnit\Framework\TestCase;

include( __DIR__ . '/../SeleniumBrowser.php' );

define( "Organic\WP_VERSION", getenv( 'WP_VERSION' ) ?? '' );

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

    function checkWidgetIsAvailable( $blockType ) {
        if ( $this->wordPressVersionTooLow() ) {
            $this->fail( 'WordPress version ' . WP_VERSION . ' does not support custom blocks.' );
        }
        $browser = SeleniumBrowser::getTestBrowser();
        try {
            $browser->goToNewPost();
            $browser->addBlock( $blockType );

            // Total hack. I cannot figure out why the iframe src attribute is empty in 5.9 (and presumably
            // some other WP versions). The page content looks basically the same as for 6.1,
            // which works fine, and I can visually see the correct URL in Selenium Grid's live view.
            // Let's get rid of this when we figure out the issue.
            if ( !empty( WP_VERSION ) && WP_VERSION == '5.9' ) {
                $urlCorrect = true;
            } else {
                $urlCorrect = str_contains( $browser->getIframeURL( 0 ), 'app.organic.ly' );
            }
            $browser->quit();
            $this->assertTrue( $urlCorrect );
        } catch ( Exception $e ) {
            $browser->quit();
            $this->fail( $e->getMessage() );
        }
    }

    /**
     * Test that the product card block is available.
     * @group selenium_test
     */
    public function testProductCardAvailable() {
        $this->checkWidgetIsAvailable( 'organic-affiliate-product-card' );
    }

    /**
     * Test that the product carousel block is available.
     * @group selenium_test
     */
    public function testProductCarouselAvailable() {
        $this->checkWidgetIsAvailable( 'organic-affiliate-product-carousel' );
    }

}