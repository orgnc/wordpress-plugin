<?php

namespace Organic;

use Exception;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverKeys;

define( "Organic\SELENIUM_URL", getenv('SELENIUM_URL' ) );
define( "Organic\WP_PORT", getenv('WP_PORT') ?? '' );
define( "Organic\WP_HOME", getenv('WP_HOME' ) . ( empty( WP_PORT ) ? '' : ':' . WP_PORT ) );

const WP_LOGIN_URL = WP_HOME . '/wp-login.php';
const WP_NEW_POST_URL = WP_HOME . '/wp-admin/post-new.php';

class SeleniumBrowser {

    private $driver;

    function __construct() {
        $this->driver = null;
    }

    /**
     * @param bool $log_into_wordpress
     * @return SeleniumBrowser
     */
    static function get_test_browser( bool $log_into_wordpress = true ) : SeleniumBrowser {
        $browser = new SeleniumBrowser();
        $browser->start();
        if ( $log_into_wordpress ) {
            $browser->log_into_wordpress();
        }
        return $browser;
    }

    /**
     * @return void
     * Do everything needed to start the test browser.
     */
    function start() {
        $this->configure_browser();
    }

    /**
     * @param float $seconds
     * @return void
     */
    function wait( float $seconds=0.2 ) {
        $int_seconds = intval( $seconds );
        $remainder = $seconds - $int_seconds;
        sleep( $int_seconds );
        usleep( $remainder * 100000 );
    }

    /**
     * @return void
     */
    function quit() {
        if ( !empty( $this->driver ) ) {
            $this->driver->quit();
        }
        $this->driver = null;
    }

    private function configure_browser() {
        $options = new ChromeOptions();
        $options->addArguments(
            [
                '--no-sandbox',
                '--disable-web-security',
                '--allow-running-insecure-content',
                '--ignore-certificate-errors'
            ]
        );
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options );
        $this->driver=RemoteWebDriver::create( SELENIUM_URL, $capabilities );
    }

    /**
     * @param $element_or_selector
     * @param $parent
     * @param $timeout
     * @param $multiple
     * @param $expected_condition
     * @return mixed
     * @throws Exception
     */
    function wait_for( $element_or_selector, $parent, $timeout = null, $multiple=false, $expected_condition=null ) {
        if ( !is_string( $element_or_selector ) ) {
            // We need to use the full namespace here. See https://www.php.net/manual/en/function.is-a.php#119972.
            $is_correct_single = !$multiple && is_a( $element_or_selector, 'Facebook\WebDriver\WebDriverElement' );
            $is_correct_multiple = $multiple && is_array( $element_or_selector ) &&
                $this->all_elements_are_web_elements( $element_or_selector );
            if ( $is_correct_single || $is_correct_multiple ) {
                return $element_or_selector;
            }
            throw new Exception( 'Wrong argument: ${$element_or_selector}' );
        }
        if ( $timeout === null ) {
            $timeout = 10;
        }
        if ( $expected_condition ) {
            $this->wait_for_condition(
                $expected_condition( WebDriverBy::cssSelector($element_or_selector ) ),
                $timeout
            );
        }
        $parent = $parent ?? $this->driver;
        if ( $multiple ) {
            return $parent->findElements( WebDriverBy::cssSelector( $element_or_selector )  );
        } elseif ( $element_or_selector[0] == '#' && !preg_match( '[ .>]', $element_or_selector ) ) {
            return $parent->findElement( WebDriverBy::id( substr( $element_or_selector, 1 ) ) );
        } else {
            return $parent->findElement( WebDriverBy::cssSelector( $element_or_selector ) );
        }
    }

    private function all_elements_are_web_elements( $array ) : bool {
        foreach ($array as $value) {
            if ( !is_a( $value, 'Facebook\WebDriver\WebDriverElement' ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $url
     * @return void
     */
    function open_page( string $url ) {
        $this->driver->get( $url );
        $this->wait_for_ajax_requests();
    }

    private function wait_for_ajax_requests() {
        $this->wait_for_document_ready();
        $this->wait();
    }

    private function wait_for_document_ready() {
        $this->driver->wait()->until(
            function ($driver) {
                return $driver->executeScript( 'return document.readyState === "complete"' );
            }
        );
    }

    /**
     * @param $condition
     * @param int $timeout
     * @return mixed
     * @throws \Facebook\WebDriver\Exception\NoSuchElementException
     * @throws \Facebook\WebDriver\Exception\TimeoutException
     */
    function wait_for_condition($condition, int $timeout = 10 ) {
        $wait = new WebDriverWait( $this->driver, $timeout );
        return $wait->until( $condition );
    }

    /**
     * @param $script
     * @return mixed
     */
    function execute_script( $script ) {
        return $this->driver->executeScript( $script );
    }

    /**
     * @param $element_or_selector
     * @param $parent
     * @param $timeout
     * @return mixed
     * @throws Exception
     */
    function click( $element_or_selector, $parent = null, $timeout = null ) {
        $element = $this->wait_for(
            $element_or_selector,
            $parent,
            $timeout,
            false,
            // We need to use the full namespace here.
            //See https://www.php.net/manual/en/language.types.callable.php#119166.
            'Facebook\WebDriver\WebDriverExpectedCondition::elementToBeClickable'
        );
        $element->click();
        return $element;
    }

    /**
     * @param string $selector
     * @param bool $multiple
     * @return mixed|null
     */
    function get_element_if_it_exists( string $selector, bool $multiple=false ) {
        try {
            return $this->wait_for( $selector, null, 1, $multiple );
        } catch ( Exception $ignore ) {
            return null;
        }
    }

    /**
     * @param $input_or_selector
     * @param $text
     * @param $parent
     * @return mixed
     * @throws Exception
     */
    function fill_text_input( $input_or_selector, $text='', $parent=null ) {
        $element = $this->click( $input_or_selector, $parent );
        $this->click( $element );
        $element->sendKeys( WebDriverKeys::CONTROL, 'a' );
        $this->driver->wait( 0.1 );
        $element->sendKeys( WebDriverKeys::DELETE );
        if ( !empty( $text ) ) {
            $element->sendKeys( $text );
        }
        return $element;
    }

    /**
     * @return void
     * @throws Exception
     */
    function log_into_wordpress() {
        $this->open_page( WP_LOGIN_URL );
        $this->fill_text_input( '#user_login', 'organic' );
        $this->fill_text_input( '#user_pass', 'organic' );
        $this->click( '#wp-submit' );
        $upgrade_database = $this->get_element_if_it_exists( '[href^="upgrade.php"]' );
        if ( !empty( $upgrade_database ) ) {
            $this->click( $upgrade_database );
        }
        $this->wait( .5 );
    }

    /**
     * @return void
     * @throws Exception
     */
    function go_to_new_post() {
        $this->open_page( WP_NEW_POST_URL );
        $modal = $this->get_element_if_it_exists( '[aria-label="Close dialog"]' );
        if ( !empty( $modal ) ) {
            // Click out of the "getting started" modal if it exists.
            $this->click( $modal );
        }
    }

    /**
     * @param string $block_type
     * @return void
     * Requires the browser to be in the editor.
     * @throws Exception
     */
    function add_block( string $block_type ) {
        // First, click the first Add block button.
        $this->click( '[aria-label="Toggle block inserter"]' );
        // Click the block search bar. Search for the block type.
        $this->fill_text_input( '.components-search-control__input', $block_type );
        // Click the icon for the block type to insert it.
        $this->click( '.editor-block-list-item-' . $block_type );
    }

    /**
     * @param int $index
     * @return mixed
     * @throws Exception
     */
    function get_iframe_url( int $index ) {
        $iframe = $this->wait_for('iframe', null, null, true)[$index];
        return $iframe->getAttribute( 'src' );
    }

}