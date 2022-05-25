<?php


namespace Organic;

/**
 * Handles adding data into the various pages on the website based on the selected
 * configuration.
 *
 * @package Organic
 */
class PageInjection {

    /**
     * @var Organic
     */
    private $organic;

    /**
     * Tracker flag to ensure we don't inject multiple copies of the Connatix player. This has
     * become an issue on the WATM template since it implements infinite scroll, which places
     * multiple "single" posts on one page and confuses this logic.
     *
     * @var bool True if Connatix player is already on the page
     */
    private $connatixInjected = false;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        $is_amp = organic_is_amp();

        if ( ! $this->organic->isEnabled() ) {
            return;
        }

        if ( $is_amp ) {
            if ( $this->organic->useAmpAds() ) {
                $this->setupAmpAdsInjector();
            }
            return;
        }

        add_action( 'wp_head', array( $this, 'injectPixel' ) );
        add_filter( 'the_content', array( $this, 'injectConnatixPlayer' ), 1 );

        if ( $this->organic->useAdsSlotsPrefill() ) {
            $this->setupAdsSlotsPrefill();
        }

        $this->setupFbiaInjection();
    }

    public function setupAmpAdsInjector() {
        $ampConfig = $this->organic->getAmpConfig();
        if ( empty( $ampConfig->forPlacement ) ) {
            return;
        }

        $adsConfig = $this->organic->getAdsConfig();

        add_filter(
            'amp_content_sanitizers',
            function ( $sanitizer_classes, $post ) use ( $ampConfig, $adsConfig ) {
                if ( ! $this->organic->eligibleForAds() ) {
                    return $sanitizer_classes;
                }

                require_once( dirname( __FILE__ ) . '/AmpAdsInjector.php' );
                $sanitizer_classes['\Organic\AmpAdsInjector'] = [
                    'ampConfig' => $ampConfig,
                    'adsConfig' => $adsConfig,
                ];
                return $sanitizer_classes;
            },
            10,
            2
        );
    }

    private function setupFbiaInjection() {
        $fbiaConfig = $this->organic->getFbiaConfig();
        if ( ! $fbiaConfig->isApplicable() ) {
            return;
        }

        add_action(
            'instant_articles_after_transform_post',
            function( $ia_post ) {
                $article = $ia_post->instant_article;
                $ia_post->instant_article = new FbiaProxy(
                    $article,
                    $this->organic
                );
            }
        );
    }

    public function setupAdsSlotsPrefill() {
        $prefillConfig = $this->organic->getPrefillConfig();
        if ( empty( $prefillConfig->forPlacement ) ) {
            return;
        }

        add_action(
            'template_redirect',
            function() {
                ob_start(
                    function ( $content ) {
                        if ( ! $content ) {
                            return $content;
                        }

                        if ( ! apply_filters( 'organic_eligible_for_ads', $this->organic->eligibleForAds( $content ) ) ) {
                            return $content;
                        }

                        $adsConfig = $this->organic->getAdsConfig();
                        $prefillConfig = $this->organic->getPrefillConfig();
                        $targeting = $this->organic->getTargeting();

                        $prefillInjector = new PrefillAdsInjector(
                            $adsConfig,
                            $prefillConfig,
                            $targeting
                        );

                        try {
                            $content = $prefillInjector->prefill( $content );
                        } catch ( \Exception $e ) {
                            \Organic\Organic::captureException( $e );
                        }

                        return $content;
                    }
                );
            }
        );
    }

    /**
     * Places a Connatix Playspace player into the article content after the first <p> tag
     */
    public function injectConnatixPlayer( $content ) {
        // Skip it if we already injected once
        if ( $this->connatixInjected ) {
            return $content;
        }

        if ( ! $this->organic->useConnatix() || ! is_single() ) {
            return $content;
        }

        // Only do this on individual post pages
        if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        // Skip out on injecting Playspace if there is already a Connatix Elements player on the
        // page.
        if ( str_contains( $content, 'connatix-elements' ) ) {
            return $content;
        }

        // Figure out if there is a paragraph to inject after. If it doesn't exist (some legacy Wordpress's) then
        // use a SPAN instead.
        $injectionPoint = strpos( $content, '</p>' );
        $injectionPointOffset = 4;
        if ( $injectionPoint === false || $injectionPoint > 1000 ) {
            $spanInjectionPoint = strpos( $content, '</span>', 200 );
            if ( $spanInjectionPoint === false ) {
                return $content;
            }
            $injectionPoint = $spanInjectionPoint;
            $injectionPointOffset = 7;
        }

        // Adjust for the length of </p> or </span>
        $injectionPoint += $injectionPointOffset;

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
                    playerId: "' . $this->organic->getConnatixPlayspaceId() . '",
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

        $connatixPlayerCode = apply_filters( 'organic_video_outstream', $connatixPlayerCode );

        return substr( $content, 0, $injectionPoint ) .
            $connatixPlayerCode .
            substr( $content, $injectionPoint );
    }

    public function injectPixel() {
        // If Organic isn't enabled, then don't bother injecting anything
        if ( ! $this->organic->isEnabled() ) {
            return;
        }

        // Checks if this is a page using a template without ads.
        if ( ! apply_filters( 'organic_eligible_for_ads', true ) ) {
            return;
        }

        if ( $this->organic->useConnatix() ) {
            echo '<script>!function(n){if(!window.cnxps){window.cnxps={},window.cnxps.cmd=[];var t=n.createElement(\'iframe\');t.display=\'none\',t.onload=function(){var n=t.contentWindow.document,c=n.createElement(\'script\');c.src=\'//cd.connatix.com/connatix.playspace.js\',c.setAttribute(\'async\',\'1\'),c.setAttribute(\'type\',\'text/javascript\'),n.body.appendChild(c)},n.head.appendChild(t)}}(document);</script>';
        }

        if ( $this->organic->getPixelPublishedUrl() || $this->organic->getSiteId() ) {
            $categoryString = '';
            $keywordString = '';
            $targeting = $this->organic->getTargeting();
            $keywords = $targeting['keywords'];
            $category = $targeting['category'];
            $gamPageId = $targeting['gamPageId'];
            $gamExternalId = $targeting['gamExternalId'];

            if ( ! is_null( $category ) ) {
                $categoryString = $category->slug;
            }

            if ( ! empty( $keywords ) ) {
                $keywordString = esc_html( implode( ',', $keywords ) );
            }
            ?>
            <script>var utils={queryString:{},init:function(){var t=this.queryString;location.search.slice(1).split("&").forEach(function(e){e=e.split("="),t[e[0]]=decodeURIComponent(e[1]||"")}),"true"===t.debug_cls&&this.logLayoutShift()},logLayoutShift:function(){function e(e){for(i=0;i<e.getEntries().length;i++){var t=e.getEntries()[i];o+=t.value,console.log("Layout shift: "+t.value+". CLS: "+o+".")}}var o=0;try{new PerformanceObserver(e).observe({type:"layout-shift",buffered:!0})}catch(t){console.log("PerformanceObserver not supported.")}},setCookie:function(e,t,o){var n,r=new Date,i=2147483647;void 0!==o&&(r.setTime(r.getTime()+24*o*60*60*1e3),i=r.toUTCString()),n="expires="+i,document.cookie=e+"="+t+";"+n+";path=/"},getCookie:function(e){var t=document.cookie.match("(^|;) ?"+e+"=([^;]*)(;|$)");return t?t[2]:null},deleteCookie:function(e){utils.setCookie(e,"",-1)},loadScript:function(e,t,o,n,r,i){if(document.querySelector("#"+t))"function"==typeof n&&n();else{var s=e.createElement("script");s.src=o,s.id=t,"function"==typeof n&&(s.onload=n),r&&Object.entries(r).forEach(function(e){s.setAttribute(e[0],e[1])}),(i=i||e.getElementsByTagName("head")[0]).appendChild(s)}}};utils.init(),window.BVTests=function(){function f(){o&&console.log.apply(null,arguments)}function e(e,t){if(!d[e]){var o=utils.queryString[h];if(o){o=o.split(",");for(var n=0;n<o.length;n++){var r=o[n].split("-");if(2===r.length&&r[0]===e)return g[e]=r[1],utils.setCookie(v+e,r[1]),void f("User bucketed from query string param:",e,r[1])}}var i=utils.getCookie(v+e);if(i&&("control"===i||i in t))f("User bucketed from cookie:",e,g[e]=i);else{d[e]=t,g[e]="control";var s,u=[];for(var a in t){s=parseInt(t[a]);for(n=0;n<s;n++)u.push(a)}var c=u.length;if(c<100)for(n=0;n<100-c;n++)u.push("control");f("weightedBuckets",u.length,u);var l=u[Math.floor(Math.random()*u.length)];f("user sampled:",s,e,l),g[e]=l,utils.setCookie(v+e,g[e]),f("user bucketed:",e,g[e])}}}function t(){var e=[];for(var t in g){var o=g[t];e.push(t+"-"+o)}return e}var d={},g={},v="bv_test__",h="debug_bv_tests",o="debug_tests"in utils.queryString;return{create:e,getValue:function(e){return g[e]},getUserBuckets:function(){return g},getTargetingValue:t}}();</script>
            <script>
                <?php if ( $this->organic->getOrganicPixelTestValue() && $this->organic->getOrganicPixelTestPercent() ) { ?>
                window.organicTestKey = "<?php echo $this->organic->getOrganicPixelTestValue(); ?>";
                BVTests.create('<?php echo $this->organic->getOrganicPixelTestValue(); ?>', {
                    enabled: <?php echo $this->organic->getOrganicPixelTestPercent(); ?>,
                });
                <?php } ?></script>
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <script>
                /* The below condition is a very specific case setup for Ads AB testing */
                try {
                    if (
                        (
                            window.organicTestKey &&
                            window.organicTestKey.indexOf('organic_adthrive') > -1 &&
                            BVTests.getValue(window.organicTestKey) === 'enabled'
                        ) || (
                            utils.queryString &&
                            utils.queryString.debug_bv_tests === 'organic_adthrive_0-enabled'
                        )
                    ) {
                        /* Loading GPT script */
                        utils.loadScript(document, 'gpt', 'https://securepubads.g.doubleclick.net/tag/js/gpt.js', null, {async:true});

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

                        /* Organic Config - to be phased in */
                        window.__organic_usp_cookie = 'ne-opt-out';
                        window.empire = window.empire || {};
                        window.empire.apps = window.empire.apps || {};
                        window.empire.apps.ads = window.empire.apps.ads || {};
                        window.empire.apps.ads.config = window.empire.apps.ads.config || {};
                    <?php if ( $this->organic->useInjectedAdsConfig() ) { ?>

                        window.empire.apps.ads.config.siteDomain = "<?php echo $this->organic->siteDomain; ?>";
                        window.empire.apps.ads.config.adConfig = <?php echo json_encode( $this->organic->getAdsConfig()->raw ); ?>;
                    <?php } ?>

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
                                utils.loadScript(document, 'prebid-library', "<?php echo $this->organic->getAdsConfig()->getPrebidBuildUrl(); ?>");
                        <?php if ( $this->organic->getSiteId() ) { /* This only works if Site ID is set up */ ?>
                            utils.loadScript(document, 'organic-sdk', "<?php echo $this->organic->sdk->getSdkUrl(); ?>");
                        <?php } else { ?>
                                utils.loadScript(document, 'track-adm-adx-pixel', "<?php echo $this->organic->getPixelPublishedUrl(); ?>");
                        <?php } ?>
                            }

                        <?php if ( $this->organic->useAdsSlotsPrefill() ) { ?>
                            loadAds();
                        <?php } else { ?>
                            setTimeout(loadAds, loadDelay);
                        <?php } ?>
                        })();
                    } else {
                        if ( window.loadAdThrive && typeof window.loadAdThrive === 'function' ) {
                            window.loadAdThrive(window, document);
                        } else if (typeof window.adthrive === 'undefined') {
                            (function(w, d) {
                                w.adthrive = w.adthrive || {};
                                w.adthrive.cmd = w.adthrive.cmd || [];
                                w.adthrive.plugin = 'adthrive-ads-<?php echo esc_js( ADTHRIVE_ADS_VERSION ); ?>';
                                w.adthrive.host = 'ads.adthrive.com';
                                w.adthrive.integration = 'plugin';

                                var commitParam = (w.adthriveCLS && w.adthriveCLS.bucket !== 'prod' && w.adthriveCLS.branch) ? '&commit=' + w.adthriveCLS.branch : '';

                                var s = d.createElement('script');
                                s.async = true;
                                s.referrerpolicy='no-referrer-when-downgrade';
                                s.src = 'https://' + w.adthrive.host + '/sites/591b55faa29bbe3a6f50b09c/ads.min.js?referrer=' + w.encodeURIComponent(w.location.href) + commitParam + '&cb=' + (Math.floor(Math.random() * 100) + 1);
                                var n = d.getElementsByTagName('script')[0];
                                n.parentNode.insertBefore(s, n);
                            })(window, document);
                        }
                    }
                } catch (err) {
                    console.log('Error in Organic Plugin Setup:', err.message);
                    (function(w, d) {
                        w.adthrive = w.adthrive || {};
                        w.adthrive.cmd = w.adthrive.cmd || [];
                        w.adthrive.plugin = 'adthrive-ads-<?php echo esc_js( ADTHRIVE_ADS_VERSION ); ?>';
                        w.adthrive.host = 'ads.adthrive.com';
                        w.adthrive.integration = 'plugin';

                        var commitParam = (w.adthriveCLS && w.adthriveCLS.bucket !== 'prod' && w.adthriveCLS.branch) ? '&commit=' + w.adthriveCLS.branch : '';

                        var s = d.createElement('script');
                        s.async = true;
                        s.referrerpolicy='no-referrer-when-downgrade';
                        s.src = 'https://' + w.adthrive.host + '/sites/591b55faa29bbe3a6f50b09c/ads.min.js?referrer=' + w.encodeURIComponent(w.location.href) + commitParam + '&cb=' + (Math.floor(Math.random() * 100) + 1);
                        var n = d.getElementsByTagName('script')[0];
                        n.parentNode.insertBefore(s, n);
                    })(window, document);
                }
            </script>
            <?php
        }
    }
}
