<?php


namespace Empire;

use DOMDocument;

/**
 * Handles adding data into the various pages on the website based on the selected
 * configuration.
 *
 * @package Empire
 */
class PageInjection {

    /**
     * @var Empire
     */
    private $empire;

    /**
     * Tracker flag to ensure we don't inject multiple copies of the Connatix player. This has
     * become an issue on the WATM template since it implements infinite scroll, which places
     * multiple "single" posts on one page and confuses this logic.
     *
     * @var bool True if Connatix player is already on the page
     */
    private $connatixInjected = false;

    public function __construct( Empire $empire ) {
        $this->empire = $empire;
        $is_amp = empire_is_amp();

        if ( ! $is_amp ) {
            add_action( 'wp_head', array( $this, 'injectPixel' ) );
            add_action( 'ads_article_header', array( $this, 'injectPresizedAdSlot' ) );
            add_filter( 'the_content', array( $this, 'injectConnatixPlayer' ), 1 );
        }
    }

    public function injectPresizedAdSlot() {
        if ( $this->empire->prefillAdSlots() ) {
            echo '<div class="ad-block"></div>';
        }
    }

    /**
     * Places a Connatix Playspace player into the article content after the first <p> tag
     */
    public function injectConnatixPlayer( $content ) {
        // Skip it if we already injected once
        if ( $this->connatixInjected ) {
            return $content;
        }

        if ( ! $this->empire->useConnatix() || ! is_single() ) {
            return $content;
        }

        // Only do this on individual post pages
        if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        // Figure out if there is a paragraph to inject after
        $injectionPoint = strpos( $content, '</p>' );
        if ( $injectionPoint === false ) {
            return $content;
        }
        // Adjust for the length of </p>
        $injectionPoint += 4;

        $this->connatixInjected = true;

        $connatixPlayerCode = '<script id="404a5343b1434e25bf26b4e6356298bc">
            var siteRootDomainParts = window.location.host.split(".");
            var siteRootDomain = window.location.host;
            if ( siteRootDomainParts.length >= 2 ) {
                siteRootDomain = siteRootDomainParts[siteRootDomainParts.length - 2] + "." + 
                    siteRootDomainParts[siteRootDomainParts.length - 1];
            }
            cnxps.cmd.push(function () {
                cnxps({
                    playerId: "' . $this->empire->getConnatixPlayspaceId() . '",
                    customParam1: window.empire.apps.ads.targeting.pageId + "",
                    customParam2: window.empire.apps.ads.targeting.section + "",
                    customParam3: window.empire.apps.ads.targeting.keywords + "",
                    settings: {
                        advertising: {
                            macros: {
                                cust_params: "site=" + siteRootDomain + 
                                    "&targeting_article=" + window.empire.apps.ads.targeting.externalId + 
                                    "&targeting_section=" + window.empire.apps.ads.targeting.section + 
                                    "&targeting_keyword=" + window.empire.apps.ads.targeting.keywords +
                                    "&article=" + window.empire.apps.ads.targeting.pageId,
                                article: window.empire.apps.ads.targeting.pageId,
                                category: window.empire.apps.ads.targeting.section,
                                keywords: window.empire.apps.ads.targeting.keywords,
                            }
                        }
                    }
                }).render("404a5343b1434e25bf26b4e6356298bc");
            });</script>';

        $connatixPlayerCode = apply_filters( 'empire_video_outstream', $connatixPlayerCode );

        return substr( $content, 0, $injectionPoint ) .
            $connatixPlayerCode .
            substr( $content, $injectionPoint );
    }

    public function injectPixel() {
        // If Empire isn't enabled, then don't bother injecting anything
        if ( ! $this->empire->isEnabled() ) {
            return;
        }

        if ( $this->empire->useConnatix() ) {
            echo '<script>!function(n){if(!window.cnxps){window.cnxps={},window.cnxps.cmd=[];var t=n.createElement(\'iframe\');t.display=\'none\',t.onload=function(){var n=t.contentWindow.document,c=n.createElement(\'script\');c.src=\'//cd.connatix.com/connatix.playspace.js\',c.setAttribute(\'async\',\'1\'),c.setAttribute(\'type\',\'text/javascript\'),n.body.appendChild(c)},n.head.appendChild(t)}}(document);</script>';
        }

        if ( $this->empire->getPixelPublishedUrl() || $this->empire->getSiteId() ) {
            $categoryString = '';
            $keywordString = '';
            $id = '';
            $gamId = '';

            if ( is_single() ) {
                $id = esc_html( get_the_ID() );
                $gamId = get_post_meta( $id, 'empire_gam_id', true );
                $category = $this->getArticlePrimaryCategory( $id );
                $keywords = get_the_tags();

                if ( $keywords && is_array( $keywords ) ) {
                    $keywords = array_map(
                        function( $tag ) {
                            return $tag->slug;
                        },
                        $keywords
                    );
                    $keywordString = esc_html( implode( ',', $keywords ) );
                }

                if ( $category && isset( $category['primary_category'] ) ) {
                    $categoryString = $category['primary_category']['obj']->slug;
                }
            } else if ( is_category() ) {
                $category = get_queried_object();
                $id = 'channel-' . $category->slug;

                $categoryString = '';
                if ( $category ) {
                    $categoryString = $category->slug;
                }
            } else if ( is_page() ) {
                $post = get_post();
                $id = 'page-' . $post->post_name;
            }

            $gamPageId = $gamId ? $gamId : $id;
            $gamExternalId = $id;
            ?>
            <script>var utils={queryString:{},init:function(){var t=this.queryString;location.search.slice(1).split("&").forEach(function(e){e=e.split("="),t[e[0]]=decodeURIComponent(e[1]||"")}),"true"===t.debug_cls&&this.logLayoutShift()},logLayoutShift:function(){function e(e){for(i=0;i<e.getEntries().length;i++){var t=e.getEntries()[i];o+=t.value,console.log("Layout shift: "+t.value+". CLS: "+o+".")}}var o=0;try{new PerformanceObserver(e).observe({type:"layout-shift",buffered:!0})}catch(t){console.log("PerformanceObserver not supported.")}},setCookie:function(e,t,o){var n,r=new Date,i=2147483647;void 0!==o&&(r.setTime(r.getTime()+24*o*60*60*1e3),i=r.toUTCString()),n="expires="+i,document.cookie=e+"="+t+";"+n+";path=/"},getCookie:function(e){var t=document.cookie.match("(^|;) ?"+e+"=([^;]*)(;|$)");return t?t[2]:null},deleteCookie:function(e){utils.setCookie(e,"",-1)},loadScript:function(e,t,o,n,r,i){if(document.querySelector("#"+t))"function"==typeof n&&n();else{var s=e.createElement("script");s.src=o,s.id=t,"function"==typeof n&&(s.onload=n),r&&Object.entries(r).forEach(function(e){s.setAttribute(e[0],e[1])}),(i=i||e.getElementsByTagName("head")[0]).appendChild(s)}}};utils.init(),window.BVTests=function(){function f(){o&&console.log.apply(null,arguments)}function e(e,t){if(!d[e]){var o=utils.queryString[h];if(o){o=o.split(",");for(var n=0;n<o.length;n++){var r=o[n].split("-");if(2===r.length&&r[0]===e)return g[e]=r[1],utils.setCookie(v+e,r[1]),void f("User bucketed from query string param:",e,r[1])}}var i=utils.getCookie(v+e);if(i&&("control"===i||i in t))f("User bucketed from cookie:",e,g[e]=i);else{d[e]=t,g[e]="control";var s,u=[];for(var a in t){s=parseInt(t[a]);for(n=0;n<s;n++)u.push(a)}var c=u.length;if(c<100)for(n=0;n<100-c;n++)u.push("control");f("weightedBuckets",u.length,u);var l=u[Math.floor(Math.random()*u.length)];f("user sampled:",s,e,l),g[e]=l,utils.setCookie(v+e,g[e]),f("user bucketed:",e,g[e])}}}function t(){var e=[];for(var t in g){var o=g[t];e.push(t+"-"+o)}return e}var d={},g={},v="bv_test__",h="debug_bv_tests",o="debug_tests"in utils.queryString;return{create:e,getValue:function(e){return g[e]},getUserBuckets:function(){return g},getTargetingValue:t}}();</script>
            <script>
                <?php if ( $this->empire->getEmpirePixelTestValue() && $this->empire->getEmpirePixelTestPercent() ) { ?>
                window.empireTestKey = "<?php echo $this->empire->getEmpirePixelTestValue(); ?>";
                BVTests.create('<?php echo $this->empire->getEmpirePixelTestValue(); ?>', {
                    enabled: <?php echo $this->empire->getEmpirePixelTestPercent(); ?>,
                });
                <?php } ?></script>
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <script async src="https://securepubads.g.doubleclick.net/tag/js/gpt.js"></script>
            <script>
                var googletag = googletag || {};
                var pbjs = pbjs || {};

                /* TrackADM Config - to be phased out */
                window.__trackadm_usp_cookie = 'ne-opt-out';
                window.tadmPageId = '<?php echo $gamPageId; ?>';
                window.tadmKeywords = '<?php echo $keywordString; ?>';
                window.tadmSection = '<?php echo $categoryString; ?>';
                window.trackADMData = {
                    tests: BVTests.getTargetingValue()
                };

                /* Empire Config - to be phased in */
                window.__empire_usp_cookie = 'ne-opt-out';
                window.empire = window.empire || {};
                window.empire.apps = window.empire.apps || {};
                window.empire.apps.ads = window.empire.apps.ads || {};
                window.empire.apps.ads.config = window.empire.apps.ads.config || {};
                window.empire.apps.ads.targeting = {
                    pageId: '<?php echo $gamPageId; ?>',
                    externalId: '<?php echo $gamExternalId; ?>',
                    keywords: '<?php echo $keywordString; ?>',
                    disableKeywordReporting: false,
                    section: '<?php echo $categoryString; ?>',
                    disableSectionReporting: false,
                    tests: BVTests.getTargetingValue(),
                }

                googletag.cmd = googletag.cmd || [];
                pbjs.que = pbjs.que || [];

                var loadDelay = 2000;

                (function() {
                    function loadAds() {
                        utils.loadScript(document, 'prebid-library', 'https://empirecdn.io/assets/prebid3.23.0.js');
                        <?php if ( $this->empire->getSiteId() ) { /* This only works if Site ID is set up */ ?>
                        <?php if ( $this->empire->getEmpirePixelTestValue() && $this->empire->getEmpirePixelTestPercent() ) { ?>
                        if ( BVTests.getValue("<?php echo $this->empire->getEmpirePixelTestValue(); ?>") == 'control' ) {
                            utils.loadScript(document, 'track-adm-adx-pixel', "<?php echo $this->empire->getPixelPublishedUrl(); ?>");
                        } else {
                            utils.loadScript(document, 'empire-sdk', "https://empirecdn.io/assets/sdk/unit-sdk.js?<?php echo $this->empire->getSiteId(); ?>");
                        }
                        <?php } else { ?>
                        utils.loadScript(document, 'empire-sdk', "https://empirecdn.io/assets/sdk/unit-sdk.js?<?php echo $this->empire->getSiteId(); ?>");
                        <?php
                        }
                        } else {
                        ?>
                        utils.loadScript(document, 'track-adm-adx-pixel', "<?php echo $this->empire->getPixelPublishedUrl(); ?>");
                        <?php } ?>
                    }
                    setTimeout(loadAds, loadDelay);
                })();
            </script>
            <?php
        }
    }

    /**
     * Get the article's primary category/all categories
     *
     * Uses Yoast SEO primary if it set, otherwise uses the first category
     *
     * @param $article_id
     * @param string $term
     * @param false $return_all_categories
     * @return array
     */
    private function getArticlePrimaryCategory(
        $article_id,
        $term = 'category',
        $return_all_categories = false
    ) {
        $result = array();

        if ( class_exists( '\WPSEO_Primary_Term' ) ) {
            // Show Primary category by Yoast if it is enabled & set
            $wpseo_primary_term = new \WPSEO_Primary_Term( $term, $article_id );
            $primary_term = get_term( $wpseo_primary_term->get_primary_term() );

            if ( ! is_wp_error( $primary_term ) ) {
                $result['primary_category'] = array(
                    'obj' => $primary_term,
                    'link' => get_term_link( $primary_term ),
                );
            }
        }

        if ( empty( $result['primary_category'] ) || $return_all_categories ) {
            $categories_list = get_the_terms( $article_id, $term );
            if ( empty( $return['primary_category'] ) && ! empty( $categories_list ) ) {
                $last_category = end( $categories_list );
                $result['primary_category'] = array(
                    'obj' => $last_category,
                    'link' => get_term_link( $last_category ),
                );  //get the first category
            }
            if ( $return_all_categories ) {
                $result['all_categories'] = array();

                array_pop( $categories_list );
                if ( ! empty( $categories_list ) ) {
                    foreach ( $categories_list as &$category ) {
                        $result['all_categories'][] = array(
                            'obj' => $category,
                            'link' => get_term_link( $category ),
                        );
                    }
                }
            }
        }

        return $result;
    }
}
