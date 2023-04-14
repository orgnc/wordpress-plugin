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
        $browser = new SeleniumBrowser();
        $browser->start();
        if ( $log_into_wordpress ) {
            $browser->log_into_wordpress();
        }
        return $browser;
    }

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

    function get_element_if_it_exists( string $selector ) {
        try {
            return $this->wait_for( '[aria-label="Close dialog"]', null, 1 );
        } catch ( WebDriverException $ignore ) {
            return null;
        }
    }

    function go_to_new_post() {
        $this->open_page( WP_NEW_POST_URL );
        fwrite(STDERR, print_r('Checking modal', TRUE));
        $modal = $this->get_element_if_it_exists( '[aria-label="Close dialog"]' );
        if ( !empty( $modal ) ) {
            // Click out of the "getting started" modal if it exists.
            $this->click( $modal );
        }
    }

    /**
     * On a post, add a block of type block_type.
     */
    function add_block( string $block_type ) {
        // First, click the first Add block button.
        $this->click( '[aria-label="Toggle block inserter"]' );
        // Click the block search bar. Search for the block type.
        $this->fill_text_input( '.components-search-control__input', $block_type );
        // Click the icon for the block type to insert it.
        $this->click( '.editor-block-list-item-' . $block_type );
    }

    function fill_text_input( $input_or_selector, $text='', $parent=null) {
        $element = $this->click( $input_or_selector, $parent );
        $this->click( $element );
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
        $this->wait_for_document_ready();
        $this->wait();
    }

    function wait_for_document_ready() {
        $this->driver->wait()->until(
            function ($driver) {
                return $driver->executeScript('return document.readyState === "complete"');
            }
        );
    }

    function wait_for_condition($condition, int $timeout = 10 ) {
        $wait = new WebDriverWait( $this->driver, $timeout );
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
            false,
            // We need to use the full namespace here.
            //See https://www.php.net/manual/en/language.types.callable.php#119166.
            'Facebook\WebDriver\WebDriverExpectedCondition::elementToBeClickable'
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
        if ( !is_string( $element_or_selector ) ) {
            // We need to use the full namespace here. See https://www.php.net/manual/en/function.is-a.php#119972.
            $is_correct_single = !$multiple && is_a( $element_or_selector, 'Facebook\WebDriver\WebDriverElement' );
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
                $expected_condition( WebDriverBy::cssSelector($element_or_selector) ),
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