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
     * @param bool logIntoWordPress
     * @return SeleniumBrowser
     */
    static function getTestBrowser( bool $logIntoWordPress = true ) : SeleniumBrowser {
        $browser = new SeleniumBrowser();
        $browser->start();
        if ( $logIntoWordPress ) {
            $browser->logIntoWordPress();
        }
        return $browser;
    }

    /**
     * @return void
     * Do everything needed to start the test browser.
     */
    function start() {
        $this->configureBrowser();
    }

    /**
     * @param float $seconds
     * @return void
     */
    function wait( float $seconds=0.2 ) {
        $intSeconds = intval( $seconds );
        $remainder = $seconds - $intSeconds;
        sleep( $intSeconds );
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

    private function configureBrowser() {
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
     * @param $elementOrSelector
     * @param $parent
     * @param $timeout
     * @param $multiple
     * @param $expectedCondition
     * @return mixed
     * @throws Exception
     */
    function waitFor( $elementOrSelector, $parent, $timeout = null, $multiple=false, $expectedCondition=null ) {
        if ( !is_string( $elementOrSelector ) ) {
            // We need to use the full namespace here. See https://www.php.net/manual/en/function.is-a.php#119972.
            $isCorrectSingle = !$multiple && is_a( $elementOrSelector, 'Facebook\WebDriver\WebDriverElement' );
            $isCorrectMultiple = $multiple && is_array( $elementOrSelector ) &&
                $this->allElementsAreWebElements( $elementOrSelector );
            if ( $isCorrectSingle || $isCorrectMultiple ) {
                return $elementOrSelector;
            }
            throw new Exception( 'Wrong argument: ${$elementOrSelector}' );
        }
        if ( $timeout === null ) {
            $timeout = 10;
        }
        if ( $expectedCondition ) {
            $this->waitForCondition(
                $expectedCondition( WebDriverBy::cssSelector($elementOrSelector ) ),
                $timeout
            );
        }
        $parent = $parent ?? $this->driver;
        if ( $multiple ) {
            return $parent->findElements( WebDriverBy::cssSelector( $elementOrSelector )  );
        } elseif ( $elementOrSelector[0] == '#' && !preg_match( '[ .>]', $elementOrSelector ) ) {
            return $parent->findElement( WebDriverBy::id( substr( $elementOrSelector, 1 ) ) );
        } else {
            return $parent->findElement( WebDriverBy::cssSelector( $elementOrSelector ) );
        }
    }

    private function allElementsAreWebElements( $array ) : bool {
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
    function openPage( string $url ) {
        $this->driver->get( $url );
        $this->waitForAjaxRequests();
    }

    private function waitForAjaxRequests() {
        $this->waitForDocumentReady();
        $this->wait();
    }

    private function waitForDocumentReady() {
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
    function waitForCondition($condition, int $timeout = 10 ) {
        $wait = new WebDriverWait( $this->driver, $timeout );
        return $wait->until( $condition );
    }

    /**
     * @param $script
     * @return mixed
     */
    function executeScript( $script ) {
        return $this->driver->executeScript( $script );
    }

    /**
     * @param $elementOrSelector
     * @param $parent
     * @param $timeout
     * @return mixed
     * @throws Exception
     */
    function click( $elementOrSelector, $parent = null, $timeout = null ) {
        $element = $this->waitFor(
            $elementOrSelector,
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
    function getElementIfItExists( string $selector, bool $multiple=false ) {
        try {
            return $this->waitFor( $selector, null, 1, $multiple );
        } catch ( Exception $ignore ) {
            return null;
        }
    }

    /**
     * @param $inputOrSelector
     * @param string $text
     * @param $parent
     * @return mixed
     * @throws Exception
     */
    function fillTextInput($inputOrSelector, string $text='', $parent=null ) {
        $element = $this->click( $inputOrSelector, $parent );
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
    function logIntoWordPress() {
        $this->openPage( WP_LOGIN_URL );
        $this->fillTextInput( '#user_login', 'organic' );
        $this->fillTextInput( '#user_pass', 'organic' );
        $this->click( '#wp-submit' );
        $upgradeDatabase = $this->getElementIfItExists( '[href^="upgrade.php"]' );
        if ( !empty( $upgradeDatabase ) ) {
            $this->click( $upgradeDatabase );
        }
        $this->wait( .5 );
    }

    /**
     * @return void
     * @throws Exception
     */
    function goToNewPost() {
        $this->openPage( WP_NEW_POST_URL );
        $modal = $this->getElementIfItExists( '[aria-label="Close dialog"]' );
        if ( !empty( $modal ) ) {
            // Click out of the "getting started" modal if it exists.
            $this->click( $modal );
        }
    }

    /**
     * @param string $blockType
     * @return void
     * Requires the browser to be in the editor.
     * @throws Exception
     */
    function addBlock( string $blockType ) {
        // First, click the first Add block button.
        $this->click( '[aria-label="Toggle block inserter"]' );
        // Click the block search bar. Search for the block type.
        $this->fillTextInput( '.components-search-control__input', $blockType );
        // Click the icon for the block type to insert it.
        $this->click( '.editor-block-list-item-' . $blockType );
    }

    /**
     * @param int $index
     * @return mixed
     * @throws Exception
     */
    function getIframe( int $index ) {
        return $this->waitFor('iframe', null, null, true )[$index];
    }

    /**
     * @param int $index
     * @return mixed
     * @throws Exception
     */
    function getIframeURL( int $index ) {
        $iframe = $this->getIframe( $index );
        return $iframe->getAttribute( 'src' );
    }

    /**
     * @param $elementOrSelector
     */
    function switchToIframe( $elementOrSelector ) {
        $this->driver->switchTo()->frame( $elementOrSelector );
        $this->waitForDocumentReady();
    }

    /**
     * @return void
     */
    function switchToDefaultContext() {
        $this->driver->switchTo()->defaultContent();
    }

    /**
     * @param int $x
     * @param int $y
     * @return void
     */
    function moveCursor( int $x = 0, int $y = 0) {
        $action = $this->driver->action();
        $action->moveByOffset($x, $y)->Perform();
    }

    /**
     * @param int $index
     * @param string $text
     * @return void
     * @throws Exception
     */
    function fillParagraphBlock( int $index = 0, string $text = '' ) {
        // WordPress does some annoying DOM manipulation, so we need to click the p element first.
        $this->click( $this->getElementIfItExists( 'p', true )[$index] );
        $this->fillTextInput( $this->getElementIfItExists( 'p', true )[$index], $text );
    }

}