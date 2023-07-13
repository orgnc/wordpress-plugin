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

    // Keep track of posts created during the tests so that we can delete them on tear down.
    static $postIDsToDelete = [];

    static function tearDownAfterClass(): void
    {
        $browser = SeleniumBrowser::getTestBrowser();
        try {
            $browser->deletePosts( WidgetsTest::$postIDsToDelete );
        } catch ( Exception $e ) {
            error_log( 'Failed to delete test posts: ' . $e->getMessage() );
        } finally {
            $browser->quit();
        }
    }

    function wordPressVersionTooLow() : bool {
        if ( !empty( WP_VERSION ) && intval( substr( WP_VERSION, 0, 1 ) ) < 5 ) {
            return true;
        }
        return false;
    }

    /**
     * When the user selects an Organic Affiliate widgets block, an IFrame with login fields will appear.
     * This function logs us in as the test user so that we can customize and insert the widget.
     * @param SeleniumBrowser $browser
     * @return void
     * @throws Exception
     */
    private function logIntoWidgetsSelectionIFrame( SeleniumBrowser $browser ) {
        $iframe = $browser->getOrganicIframe();
        $browser->switchToIframe( $iframe );
        // Log in with test account.
        $browser->fillTextInput( '#email', ORGANIC_TEST_USER_EMAIL );
        $browser->fillTextInput( '#password', ORGANIC_TEST_USER_PASSWORD );
        $browser->click( '#signin-button' );
    }

    /**
     * Search for and select the Test Product in our Organic Affiliate widgets IFrame.
     * @param SeleniumBrowser $browser
     * @return void
     * @throws Exception
     */
    private function selectTestProduct( SeleniumBrowser $browser ) {
        $test_product_guid = TEST_PRODUCT_GUID;
        $browser->fillTextInput( '#affiliate-product-search-entry', 'Selenium Test Product' );
        try {
            $browser->click( "[data-test-element=\"affiliate-product-select-{$test_product_guid}\"]" );
        } catch ( Exception $e ) {
            $browser->quit();
            $this->fail( 'Was Organic Demo\'s Selenium Test Product deleted? ' . $e->getMessage() );
        }
    }

    /**
     * Search for and select the Test Product offer link in our Organic Affiliate widgets IFrame.
     * @param SeleniumBrowser $browser
     * @return void
     * @throws Exception
     */
    private function selectTestProductOfferLink( SeleniumBrowser $browser ) {
        $browser->fillTextInput( '#affiliate-product-search-entry', 'Selenium Test Product' );
        $browser->click( '[data-test-element="affiliate-offer-button"]' );
    }

    /**
     * Certain selectors are based on, e.g., product-card rather than organic-affiliate-product-card.
     * This returns the shorter version.
     * @param string $blockType
     * @return false|string
     */
    private function truncateBlockType( string $blockType ) {
        return substr( $blockType, strlen('organic-affiliate-' ) );
    }

    /**
     * Once the widget is customized as wanted, confirm and insert the widget into the editor.
     * @param SeleniumBrowser $browser
     * @param string $blockType
     * @return void
     * @throws Exception
     */
    private function confirmWidgetSelection( SeleniumBrowser $browser, string $blockType ) {
        $blockTypeTruncated = $this->truncateBlockType( $blockType );
        $browser->click( "[data-test-element=\"affiliate-create-{$blockTypeTruncated}-confirm\"]" );
        // The IFrame will disappear and the widget will be inserted, so we toggle Selenium out of the IFrame.
        $browser->switchToDefaultContext();
    }

    /**
     * Find and return the Organic Affiliate widgets iframe.
     * @return mixed
     * @throws Exception
     */
    function getRenderedWidgetIFrame( SeleniumBrowser $browser, string $blockType ) {
        $blockTypeTruncated = $this->truncateBlockType( $blockType );
        $browser->wait();
        return $browser->waitFor(
            "div[data-organic-affiliate-integration={$blockTypeTruncated}] > iframe", null
        );
    }

    /**
     * @param $blockType
     * @return void
     */
    function checkWidgetInsertion( $blockType ) {
        if ( $this->wordPressVersionTooLow() ) {
            $this->fail( 'WordPress version ' . WP_VERSION . ' does not support custom blocks.' );
        }
        $browser = SeleniumBrowser::getTestBrowser();
        try {
            $browser->goToNewPost();
            $browser->addBlock( $blockType );
            $this->logIntoWidgetsSelectionIFrame( $browser );
            $this->selectTestProduct( $browser );
            $this->confirmWidgetSelection( $browser, $blockType );
            // First, check that the widget is rendered (as an IFrame) upon insertion.
            $this->getRenderedWidgetIFrame( $browser, $blockType );
            // Next, we'll check that the widget is still rendered after refreshing the page.
            // To refresh the page, we need to save.
            $browser->savePostAsDraft();
            // We mark the post for eventual deletion when we tear down the tests.
            WidgetsTest::$postIDsToDelete[] = $browser->getCurrentPostID();
            $browser->refreshPage();
            // Check to see that the widget is rendered after refreshing the page.
            $this->getRenderedWidgetIFrame( $browser, $blockType );

            $browser->quit();
            $this->assertTrue( true );
        } catch ( Exception $e ) {
            $browser->quit();
            $this->fail( $e->getMessage() );
        }
    }

    /**
     * Test that the product card block is available.
     * @group selenium_test
     */
    public function testProductCard() {
        $this->checkWidgetInsertion( 'organic-affiliate-product-card' );
    }

    /**
     * Test that the product carousel block is available.
     * @group selenium_test
     */
    public function testProductCarousel() {
        $this->checkWidgetInsertion( 'organic-affiliate-product-carousel' );
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
            // The Organic IFrame should appear. We log in.
            $this->logIntoWidgetsSelectionIFrame( $browser );
            // Then we search for and select the test product.
            $this->selectTestProductOfferLink( $browser );
            // We move out of the iframe and check that the link has been added for the test product.
            $browser->switchToDefaultContext();
            $browser->wait();
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