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
            if ( $this->organic->isAffiliateAppEnabled() ) {
                $this->setupAmpAffiliateInjector();
            }
            return;
        }

        add_action( 'wp_head', [ $this, 'injectBrowserSDK' ] );
        add_action( 'rss2_item', [ $this, 'injectRssImage' ] );
        add_action( 'rss2_ns', [ $this, 'injectRssNs' ] );

        if ( $this->organic->useAdsSlotsPrefill() ) {
            $this->setupAdsSlotsPrefill();
        }
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

    public function setupAmpAffiliateInjector() {
        add_filter(
            'amp_content_sanitizers',
            function ( $sanitizer_classes, $post ) {
                require_once( dirname( __FILE__ ) . '/AmpAffiliateInjector.php' );
                $sanitizer_classes['\Organic\AmpAffiliateInjector'] = [];
                return $sanitizer_classes;
            },
            10,
            2
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

    public function injectBrowserSDK() {
        // If Organic isn't enabled, then don't bother injecting anything
        if ( ! $this->organic->isEnabled() ) {
            return;
        }

        if ( ! $this->organic->getSiteId() ) {
            return;
        }

        $this->injectPrefetchHeaders();
        $this->injectBrowserSDKConfiguration();
        if ( ! $this->organic->useSplitTest() ) {
            // If we are not in test mode then we need to be loading up our ad stack as quickly as possible, which
            // means that we should do it with <script> tags directly.
            wp_print_script_tag(
                [
                    'src' => 'https://securepubads.g.doubleclick.net/tag/js/gpt.js',
                    'id' => 'gpt',
                    'async' => true,
                ]
            );
            wp_print_script_tag(
                [
                    'src' => $this->organic->getAdsConfig()->getPrebidBuildUrl(),
                    'id' => 'organic-prebid',
                    'async' => true,
                ]
            );
            wp_print_script_tag(
                [
                    'src' => $this->organic->getSdkUrl(),
                    'id' => 'organic-sdk',
                    'async' => true,
                ]
            );
        } else {
            $this->injectSplitTestUtils(); ?>
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <script>
                window.organicTestKey = "<?php echo esc_js( $this->organic->getOrganicPixelTestValue() ); ?>";
                BVTests.create('<?php echo esc_js( $this->organic->getOrganicPixelTestValue() ); ?>', {
                    enabled: <?php echo esc_js( $this->organic->getOrganicPixelTestPercent() ); ?>,
                });

                if ( window.organicTestKey && BVTests.getValue(window.organicTestKey) === 'control' ) {
                    // The below condition is a very specific case setup for Ads AB testing
                    // Do nothing here, but rely on third party code to detect the use case and load the ads their way
                } else {
                    utils.loadScript('gpt', 'https://securepubads.g.doubleclick.net/tag/js/gpt.js');
                    utils.loadScript('organic-prebid', "<?php echo esc_url( $this->organic->getAdsConfig()->getPrebidBuildUrl() ); ?>");
                    utils.loadScript('organic-sdk', "<?php echo esc_url( $this->organic->getSdkUrl() ); ?>");
                }
            </script>
            <?php
        }
    }

    public function injectPrefetchHeaders() {
        ?>
        <link rel="preconnect" href="https://organiccdn.io/" crossorigin>
        <link rel="dns-prefetch" href="https://organiccdn.io/">
        <link rel="preconnect" href="https://securepubads.g.doubleclick.net/" crossorigin>
        <link rel="dns-prefetch" href="https://securepubads.g.doubleclick.net/">
        <link rel="preconnect" href="https://c.amazon-adsystem.com/" crossorigin>
        <link rel="dns-prefetch" href="https://c.amazon-adsystem.com/">
        <?php
    }

    public function injectBrowserSDKConfiguration() {
        ?>
        <script>
            window.__organic_usp_cookie = 'ne-opt-out';
            window.__trackadm_usp_cookie = 'ne-opt-out';
            window.__empire_usp_cookie = 'ne-opt-out';

            window.empire = window.empire || {};
            window.empire.cmd = window.empire.cmd || [];
            window.empire.disableSDKAutoInitialization = true;
            // disable calling `processPage` during `init`
            // TODO-sdk: do not call `processPage` if `disableSDKAutoInitialization` is true
            window.empire._disableAffiliateAutoProcessing = true;
        </script>
        <?php

        if ( $this->organic->useInjectedAdsConfig() ) {
            // TODO: get rid of it after switch to the SDKv2
            ?>
            <script>
                // Using deprecated configuration method to inject AdConfig for SDKv1
                window.empire.apps = window.empire.apps || {};
                window.empire.apps.ads = window.empire.apps.ads || {};
                (function (){
                    var siteDomain = "<?php echo esc_js( $this->organic->siteDomain ); ?>";
                    var adConfig = <?php echo json_encode( $this->organic->getAdsConfig()->raw ); ?>;
                    adConfig.site = siteDomain;
                    window.empire.apps.ads.config = adConfig;
                })();
            </script>
            <?php
        }

        // Core SDK is always enabled in SDKv2 (for SDKv1 it will be just undefined)
        ?>
        <script>
            window.empire.cmd.push(function(apps) {
                if (!apps.core) return;
                apps.core.init();
            });
        </script>
        <?php

        // Allow to disable Ads SDK by hook
        if ( apply_filters( 'organic_eligible_for_ads', true ) ) {
            $sectionString = '';
            $keywordString = '';
            $targeting = $this->organic->getTargeting();
            $keywords = $targeting['keywords'];
            $sections = $targeting['sections'];
            $gamPageId = $targeting['gamPageId'];
            $gamExternalId = $targeting['gamExternalId'];

            if ( ! empty( $sections ) ) {
                $sectionString = esc_html( implode( ',', $sections ) );
            }

            if ( ! empty( $keywords ) ) {
                $keywordString = esc_html( implode( ',', $keywords ) );
            }

            // Configure Ads SDK
            ?>
            <script>
                window.empire.cmd.push(function(apps) {
                    var ads = apps.ads;
                    if (!ads || !ads.isEnabled()) return;

                    ads.init();
                    ads.setTargeting({
                        pageId: '<?php echo esc_js( $gamPageId ); ?>',
                        externalId: '<?php echo esc_js( $gamExternalId ); ?>',
                        keywords: '<?php echo esc_js( $keywordString ); ?>',
                        disableKeywordReporting: false,
                        section: '<?php echo esc_js( $sectionString ); ?>',
                        disableSectionReporting: false,
                        tests: window.BVTests ? window.BVTests.getTargetingValue() : undefined,
                    });
                    ads.waitForPageLoad(function(){
                        ads.initializeAds();
                    });
                });
            </script>
            <?php
        }

        // Allow to disable Affiliate SDK by hook
        if ( $this->organic->isAffiliateAppEnabled() && apply_filters( 'organic_eligible_for_affiliate', true ) ) {
            ?>
            <script>
                window.empire.cmd.push(function(apps) {
                    var affiliate = apps.affiliate;
                    if (!affiliate || !affiliate.isEnabled()) return;

                    affiliate.init();
                    affiliate.waitForPageLoad(function(){
                        affiliate.processPage();
                    });
                });
            </script>
            <?php
        }
    }

    // TODO: refactor/cleanup
    public function injectSplitTestUtils() {
        ?>
        <script>
            var utils = {
                queryString: {},
                init: function () {
                    var t = this.queryString;
                    location.search.slice(1).split("&").forEach(function (e) {
                        e = e.split("="),
                            t[e[0]] = decodeURIComponent(e[1] || "")
                    }),
                    "true" === t.debug_cls && this.logLayoutShift()
                },
                logLayoutShift: function () {
                    function e(e) {
                        for (i = 0; i < e.getEntries().length; i++) {
                            var t = e.getEntries()[i];
                            o += t.value,
                                console.log("Layout shift: " + t.value + ". CLS: " + o + ".")
                        }
                    }

                    var o = 0;
                    try {
                        new PerformanceObserver(e).observe({
                            type: "layout-shift",
                            buffered: !0
                        })
                    } catch (t) {
                        console.log("PerformanceObserver not supported.")
                    }
                },
                setCookie: function (e, t, o) {
                    var n, r = new Date, i = 2147483647;
                    void 0 !== o && (r.setTime(r.getTime() + 24 * o * 60 * 60 * 1e3),
                        i = r.toUTCString()),
                        n = "expires=" + i,
                        document.cookie = e + "=" + t + ";" + n + ";path=/"
                },
                getCookie: function (e) {
                    var t = document.cookie.match("(^|;) ?" + e + "=([^;]*)(;|$)");
                    return t ? t[2] : null
                },
                deleteCookie: function (e) {
                    utils.setCookie(e, "", -1)
                },
                loadScript: function (id, src) {
                    if (document.getElementById(id)) return;
                    var script = document.createElement('script');
                    script.id = id;
                    script.src = src;
                    script.async = true;
                    document.getElementsByTagName('head')[0].appendChild(script);
                }
            };
            utils.init();

            window.BVTests = function() {
                function f() {
                    o && console.log.apply(null, arguments)
                };
                function e(e, t) {
                    if (!d[e]) {
                        var o = utils.queryString[h];
                        if (o) {
                            o = o.split(",");
                            for (var n = 0; n < o.length; n++) {
                                var r = o[n].split("-");
                                if (2 === r.length && r[0] === e)
                                    return g[e] = r[1],
                                        utils.setCookie(v + e, r[1]),
                                        void f("User bucketed from query string param:", e, r[1])
                            }
                        }
                        var i = utils.getCookie(v + e);
                        if (i && ("control" === i || i in t))
                            f("User bucketed from cookie:", e, g[e] = i);
                        else {
                            d[e] = t,
                                g[e] = "control";
                            var s, u = [];
                            for (var a in t) {
                                s = parseInt(t[a]);
                                for (n = 0; n < s; n++)
                                    u.push(a)
                            }
                            var c = u.length;
                            if (c < 100)
                                for (n = 0; n < 100 - c; n++)
                                    u.push("control");
                            f("weightedBuckets", u.length, u);
                            var l = u[Math.floor(Math.random() * u.length)];
                            f("user sampled:", s, e, l),
                                g[e] = l,
                                utils.setCookie(v + e, g[e]),
                                f("user bucketed:", e, g[e])
                        }
                    }
                };
                function t() {
                    var e = [];
                    for (var t in g) {
                        var o = g[t];
                        e.push(t + "-" + o)
                    }
                    return e
                };
                var d = {}
                    , g = {}
                    , v = "bv_test__"
                    , h = "debug_bv_tests"
                    , o = "debug_tests"in utils.queryString;

                return {
                    create: e,
                    getValue: function(e) {
                        return g[e]
                    },
                    getUserBuckets: function() {
                        return g
                    },
                    getTargetingValue: t
                }
            }();
        </script>
        <?php
    }

    /**
     * Adds in media URLs to the RSS feed to allow outstream players to rely on the feed for slideshows
     *
     * @return void
     */
    public function injectRssImage() {
        if ( $this->organic->getFeedImages() && has_post_thumbnail() ) {
            echo '<media:content url="' . esc_url( get_the_post_thumbnail_url( null, 'medium' ) ) . '" medium="image" />';
        }
    }

    /**
     * Adds in support for media URLs via an RSS2 extension
     *
     * @return void
     */
    public function injectRssNs() {
        if ( $this->organic->getFeedImages() ) {
            echo 'xmlns:media="http://search.yahoo.com/mrss/"';
        }
    }
}
