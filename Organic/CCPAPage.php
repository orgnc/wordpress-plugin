<?php

namespace Organic;

use stdClass;

/**
 * Manages the ads.txt data and presentation
 *
 * @package Organic
 */
class CCPAPage {


    /**
     * @var Organic
     */
    private Organic $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;

        if ( $this->organic->getCmp() ) {
            add_action( 'init', array( $this, 'show' ) );
            add_action( 'wp_head', array( $this, 'head' ), 100 );
            add_action( 'wp_footer', array( $this, 'footer' ), 100 );
            add_action( 'footer_extra_nav', array( $this, 'footerExtraNav' ), 100 );
            add_filter( 'wp_get_nav_menu_items', array( $this, 'addFooterMenuItem' ), 20, 2 );
        }
    }

    /**
     * Hook for injecting One Trust button in footer navigation
     */
    public function footerExtraNav() {
        if ( $this->organic->useCmpOneTrust() ) {
            echo '<button id="ot-sdk-btn" class="ot-sdk-show-settings">Cookie Settings</button>';
        }
    }

    /**
     * This hook is only helpful on traditional / default templates.
     *
     * @return string new Menu item
     */
    public function footer() {
        if ( $this->organic->useCmpBuiltIn() ) {
            echo '<a href="/do-not-collect">Do Not Sell My Personal Information</a>';
        }
    }

    /**
     * Injects One Trust JS snippets into the header if enabled and configured
     *
     * @return string
     */
    public function head() {
        if ( $this->organic->useCmpOneTrust() ) {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
            echo '<script src="https://cdn.cookielaw.org/scripttemplates/otSDKStub.js"'
               . ' type="text/javascript" charset="UTF-8" data-domain-script="'
               . $this->organic->getOneTrustId()
               . '" ></script><script type="text/javascript">function OptanonWrapper(){}</script>';
        }
    }

    /**
     * Used within our watm template
     * @param $items
     * @param $menu
     * @return mixed
     */
    public function addFooterMenuItem( $items, $menu ) {
        if ( $menu->slug === 'footer-menu' ) {
            if ( $this->organic->useCmpBuiltIn() ) {
                $items[] = $this->customNavMenuItem(
                    'Do Not Sell My Personal Information',
                    '/do-not-collect',
                    100
                );
            }
        }

        return $items;
    }

    /**
     * Simple helper function for make menu item objects
     *
     * @param $title      - menu item title
     * @param $url        - menu item url
     * @param $order      - where the item should appear in the menu
     * @param int $parent - the item's parent item
     * @return \stdClass
     */
    public function customNavMenuItem( $title, $url, $order, $parent = 0 ) {
        $item = new stdClass();
        $item->ID = 1000000 + $order + $parent;
        $item->db_id = $item->ID;
        $item->title = $title;
        $item->url = $url;
        $item->menu_order = $order;
        $item->menu_item_parent = $parent;
        $item->type = '';
        $item->object = '';
        $item->object_id = '';
        $item->classes = array();
        $item->target = '';
        $item->attr_title = '';
        $item->description = '';
        $item->xfn = '';
        $item->status = '';
        return $item;
    }

    public function show() {
        if ( isset( $_SERVER ) && $_SERVER['REQUEST_URI'] === '/do-not-collect' ) {
            $enabled = $this->organic->getOption( 'organic::enabled' );

            if ( $this->organic->useCmpBuiltIn() && $enabled ) {
                header( 'content-type: text/html; charset=UTF-8' );
                header( 'cache-control: public, max-age=86400' );
                $contents = <<<EOF

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Do Not Sell My Personal Information</title>
    <style>
        body {
            background-color: #efefef;
        }
        div {
            background-color: white;
            text-align: center;
            margin: auto;
            padding: 40px;
        }
        .nav {
            text-align: left;
        }
        a:link, a:visited {
            color: #313131;
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div>
        <p class="nav"><a href="/">&lt; Back to the Site</a></p>
        <h1>Do Not Sell My Personal Information</h1>
        <p><label>Please check this box to opt out of any personal information being shared with our
        partner advertisers: <input type="checkbox" id="opt_out" onchange="update()" /></label></p>
        <p>
            <a href="/privacy-policy/">Privacy Policy</a>
            <a href="/privacy-policy/#california">Your California Privacy Rights</a>
            <a href="/terms/">Terms of Service</a>
        </p>
    </div>
<script>
    var checkbox = document.getElementById("opt_out");

    function update() {
        if ( checkbox.checked )
            document.cookie = "ne-opt-out=0; expires=Fri, 1 Jan 2038 12:00:00 UTC";
        else
            document.cookie = "ne-opt-out=; expires=Fri, 1 Jan 2010 12:00:00 UTC";
        alert("Collection Status Updated");
    }

    function getCookie(name) {
        var parts = ("; " + document.cookie).split("; " + name + "=");
        if (parts.length === 2)
            return parts.pop().split(";").shift();
        else
            return null;
    }

    var status = getCookie("ne-opt-out");
    if ( status === "0" )
        checkbox.checked = true;
    else
        checkbox.checked = false;
</script>
</body>
</html>
EOF;

                echo $contents;
                exit;
            }
        }
    }
}
