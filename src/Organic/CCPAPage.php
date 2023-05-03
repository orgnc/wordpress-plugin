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
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;

        if ( ! empty( $this->organic->getCmp() ) ) {
            add_action( 'init', [ $this, 'show' ] );
            add_action( 'wp_footer', [ $this, 'footer' ], 100 );
            add_action( 'footer_extra_nav', [ $this, 'footerExtraNav' ], 100 );
            add_filter( 'wp_get_nav_menu_items', [ $this, 'addFooterMenuItem' ], 20, 2 );

            if ( $this->organic->useCmpOneTrust() ) {
                add_filter( 'script_loader_tag', [ $this, 'oneTrustScriptKey' ], 10, 3 );
                add_action( 'wp_enqueue_scripts', [ $this, 'enqueueOneTrust' ] );
            }
        }
    }

    public function enqueueOneTrust() {
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
        wp_enqueue_script(
            'one-trust',
            'https://cdn.cookielaw.org/scripttemplates/otSDKStub.js',
            [],
            null
        );
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
     * @param $tag
     * @param $handle
     * @param $src
     * @return mixed|string
     */
    public function oneTrustScriptKey( $tag, $handle, $src ) {
        if ( $handle === 'one-trust' ) {
            $main_tag = wp_get_script_tag(
                [
                    'src' => $src,
                    'data-domain-script' => $this->organic->getOneTrustId(),
                ]
            );
            $inline_tag = wp_get_inline_script_tag( 'function OptanonWrapper(){}' );
            return $main_tag . $inline_tag;
        }
        return $tag;
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
        $item->classes = [];
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
                ?>
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
                <?php
                exit;
            }
        }
    }
}
