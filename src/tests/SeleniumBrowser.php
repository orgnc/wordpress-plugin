<?php

namespace Organic;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Chrome\ChromeOptions;
use PHPUnit\Util\Exception;
use Facebook\WebDriver\WebDriverKeys;

define("Organic\SELENIUM_URL", getenv('SELENIUM_URL'));
define("Organic\WP_PORT", getenv('WP_PORT') ?? '');
define("Organic\WP_HOME", getenv('WP_HOME') . ( empty( WP_PORT ) ? '' : ':' . WP_PORT ) );

const WP_LOGIN_URL = WP_HOME . '/wp-login.php';
const WP_NEW_POST_URL = WP_HOME . '/wp-admin/post-new.php';

class SeleniumBrowser {

    private $driver;

    function __construct() {
        $this->driver = null;
    }

    static function get_test_browser( bool $log_into_wordpress = true ) {
        fwrite(STDERR, print_r('\nHere we go!', TRUE));
        fwrite(STDERR, print_r(getenv('WP_HOME'), TRUE));
        $browser = new SeleniumBrowser();
        $browser->start();
        if ( $log_into_wordpress ) {
            $browser->log_into_wordpress();
        }
        return $browser;
    }

    function log_into_wordpress() {
        $this->open_page( WP_LOGIN_URL );
        $this->wait(.5);
        $this->fill_text_input( '#user_login', 'organic' );
        $this->fill_text_input( '#user_pass', 'organic' );
        $this->click( '#wp-submit' );
        $this->wait( .5 );
    }

    function configure_browser() {
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
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        print('SELENIUM_URL');
        print(SELENIUM_URL);
        $this->driver=RemoteWebDriver::create( SELENIUM_URL, $capabilities );
    }

    function go_to_new_post() {
        fwrite(STDERR, print_r('Going to new post', TRUE));
        fwrite(STDERR, print_r(WP_NEW_POST_URL, TRUE));
        $this->open_page( WP_NEW_POST_URL );
        fwrite(STDERR, print_r('Checking modal', TRUE));
        try {
            // Click out of the "getting started" modal if it exists.
            $this->click( '[aria-label="Close dialog"]' );
        } catch(WebDriverException $ignore) {
            // If it doesn't exist, continue.
        }
    }

    /**
     * On a post, add a block of type block_type.
     */
    function add_block( string $block_type ) {
        $this->wait(2);
        // First, click the first Add block button.
        fwrite(STDERR, print_r('Clicking Add block', TRUE));
        $this->click( '[aria-label="Toggle block inserter"]' );
        fwrite(STDERR, print_r('Clicked Add block', TRUE));
        $this->wait( 3 );
        // Click the block search bar. Search for the block type.
        $this->fill_text_input( '.components-search-control__input', $block_type );
        $this->wait( 1 );
        // Click the icon for the block type to insert it.
        $this->click( '.editor-block-list-item-' . $block_type );
    }

    function select_block_type() {

    }

    function fill_text_input( $input_or_selector, $text='', $parent=null) {
        $element = $this->click( $input_or_selector, $parent );
        $element->sendKeys( WebDriverKeys::CONTROL, 'a' );
        $this->driver->wait( 0.1 );
        $element->sendKeys( WebDriverKeys::DELETE);
        if ( !empty( $text ) ) {
            $element->sendKeys( $text );
        }
        return $element;
    }

    function start() {
        $this->configure_browser();
    }

    function wait( $seconds=0.2 ) {
        sleep( $seconds );
    }

    function quit() {
        if ( !empty( $this->driver ) ) {
            $this->driver->quit();
        }
        $this->driver = null;
    }

    function open_page( string $url ) {
        $this->driver->get( $url );
        $this->wait_for_ajax_requests();
    }

    function wait_for_ajax_requests() {
        # $this->wait_for_document_ready();
        $this->wait(4);
    }

    function wait_for_document_ready() {
        $this->driver->wait()->until(
            function ($driver) {
                return $driver->executeScript('return document.readyState === "complete"');
            }
        );
    }

    function wait_for_condition( $condition, int $timeout = 10 ) {
        $wait = new WebDriverWait( $condition, $timeout );
        return $wait->until( $condition );
    }

    function execute_script( $script ) {
        return $this->driver->executeScript( $script );
    }

    function click( $element_or_selector, $parent = null, $timeout = null ) {
        $element = $this->wait_for(
            $element_or_selector,
            $parent,
            $timeout,
            false
        );
        $element->click();
        return $element;
    }

    function all_elements_are_web_elements( $array ) {
        foreach ($array as $value) {
            if ( !is_a( $value, 'WebDriverElement' ) ) {
                return false;
            }
        }
        return true;
    }

    function wait_for( $element_or_selector, $parent, $timeout, $multiple=false, $expected_condition=null ) {
        fwrite(STDERR, print_r($element_or_selector, TRUE));
        fwrite(STDERR, print_r(gettype($element_or_selector), TRUE));
        if ( !is_string( $element_or_selector ) ) {
            $is_correct_single = !$multiple && is_a( $element_or_selector, 'WebDriverElement' );
            $is_correct_multiple = $multiple && is_array( $element_or_selector ) && $this->all_elements_are_web_elements( $element_or_selector );
            if ( $is_correct_single || $is_correct_multiple ) {
                return $element_or_selector;
            }
            throw new Exception( 'Wrong argument: ${$element_or_selector}' );
        }
        if ( !$timeout ) {
            $timeout = 10;
        }
        if ( $expected_condition ) {
            $this->wait_for_condition(
                $expected_condition( 'WebDriverBy::cssSelector', $element_or_selector ),
                $timeout
            );
        }
        $parent = $parent ?? $this->driver;
        if ( $multiple ) {
            return $parent->findElements( WebDriverBy::cssSelector( $element_or_selector )  );
        } elseif ( $element_or_selector[0] == '#' ) {
            return $parent->findElement( WebDriverBy::id( substr( $element_or_selector, 1 ) ) );
        } else {
            return $parent->findElement( WebDriverBy::cssSelector( $element_or_selector ) );
        }

    }

    function get_iframe_url( int $index ) {
        $iframe = $this->wait_for('iframe', null, null, true)[$index];
        return $iframe->getAttribute('src');
    }

}