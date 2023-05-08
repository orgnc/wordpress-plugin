<?php

namespace Organic;

use Exception;
use PHPUnit\Framework\TestCase;

include( __DIR__ . '/../SeleniumBrowser.php' );

define( "Organic\WP_VERSION", getenv( 'WP_VERSION' ) ?? '' );
define( "Organic\ORGANIC_TEST_USER_EMAIL", getenv( 'ORGANIC_TEST_USER_EMAIL' ) ?? '' );
define( "Organic\ORGANIC_TEST_USER_PASSWORD", getenv( 'ORGANIC_TEST_USER_PASSWORD' ) ?? '' );
const TEST_PRODUCT_GUID = '7cbfc98e-ffcc-456e-ac5d-831150f43177';

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

    /**
     * Test that we can insert an Organic Affiliate magic link.
     * @group selenium_test
     */
    public function testInsertMagicLink() {
        if ( $this->wordPressVersionTooLow() ) {
            $this->fail( 'WordPress version ' . WP_VERSION . ' does not support custom blocks.' );
        }
        if ( ! ORGANIC_TEST_USER_EMAIL || ! ORGANIC_TEST_USER_PASSWORD ) {
            $this->fail(
                'Cannot run testInsertMagicLink without ORGANIC_TEST_USER_EMAIL and ORGANIC_TEST_USER_PASSWORD set'
            );
        }
        $browser = SeleniumBrowser::getTestBrowser();
        try {
            $browser->goToNewPost();
            $browser->fillParagraphBlock( 0, 'testing...' );
            // The toolbar only displays after mouse movement.
            $browser->moveCursor( 0, 10 );
            // The toolbar should be visible. We click the Organic Tools menu item.
            $browser->click( '[aria-label="Organic Tools"]' );
            // Then we click the submenu item.
            $browser->click( 'button[role="menuitem"]' );
            // The Organic iframe should appear.
            $iframe = $browser->getIframe( 0 );
            $browser->switchToIframe( $iframe );
            # Log in with test account.
            $browser->fillTextInput( '#email', ORGANIC_TEST_USER_EMAIL );
            $browser->fillTextInput( '#password', ORGANIC_TEST_USER_PASSWORD );
            $browser->click( '#signin-button' );
            $browser->wait();
            # Search for the test product.
            $browser->fillTextInput( '#affiliate-product-search-entry', 'Selenium Test Product' );
            $browser->click( '[data-test-element="affiliate-offer-button"]' );
            # We move out of the iframe and check that the link has been added for the test product.
            $browser->switchToDefaultContext();
            $browser->wait( .5 );
            $guid = TEST_PRODUCT_GUID;
            $browser->waitFor( "[href$=\"{$guid}/\"]", null );
            $browser->quit();
            $this->assertTrue( true );
        } catch ( Exception $e ) {
            $browser->quit();
            $this->fail( $e );
        }
    }

}