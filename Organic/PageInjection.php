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
        if ( ! $organic->isEnabledAndConfigured() ) {
            return;
        }
        $this->organic = $organic;

        if ( organic_is_amp() && $this->organic->useAmp() ) {
            $this->setupAdsAmp();
            $this->setupAffiliateAmp();
            return;
        }

        if ( $this->organic->usePrefill() ) {
            $this->setupAdsPrefill();
        }

        add_action( 'wp_head', [ $this, 'injectBrowserSDK' ] );

        if ( $this->organic->useFeedImages() ) {
            add_action( 'rss2_item', [ $this, 'injectRssImage' ] );
            add_action( 'rss2_ns', [ $this, 'injectRssNs' ] );
        }
    }

    public function setupAdsAmp() {
        if ( ! $this->organic->useAds()) {
            return;
        }

        $ampConfig = $this->organic->getAmpConfig();
        if ( empty( $ampConfig->forPlacement ) ) {
            return;
        }

        $adsConfig = $this->organic->getAdsConfig();

        add_filter(
            'amp_content_sanitizers',
            function ( $sanitizer_classes, $post ) use ( $ampConfig, $adsConfig ) {
                if ( ! $this->organic->useAdsOnPage() ) {
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

    public function setupAffiliateAmp() {
        if ( ! $this->organic->useAffiliate() ) {
            return;
        }

        add_filter(
            'amp_content_sanitizers',
            function ( $sanitizer_classes, $post ) {
                if ( ! $this->organic->useAffiliateOnPage() ) {
                    return $sanitizer_classes;
                }

                require_once( dirname( __FILE__ ) . '/AmpAffiliateInjector.php' );
                $sanitizer_classes['\Organic\AmpAffiliateInjector'] = [];
                return $sanitizer_classes;
            },
            10,
            2
        );
    }

    public function setupAdsPrefill() {
        if ( ! $this->organic->useAds()) {
            return;
        }

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

                        if ( ! $this->organic->useAdsOnPage( $content ) ) {
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
        $this->injectPrefetchHeaders();
        $this->injectBrowserSDKConfiguration();
        if ( ! $this->organic->useSplitTest() ) {
            // If we are not in test mode then we need to be loading up our ad stack as quickly as possible, which
            // means that we should do it with <script> tags directly.
            if ($this->organic->useAdsOnPage()) {
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
            }
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
                    <?php if ( $this->organic->useAdsOnPage() ) { ?>
                        utils.loadScript('gpt', 'https://securepubads.g.doubleclick.net/tag/js/gpt.js');
                        utils.loadScript('organic-prebid', "<?php echo esc_url( $this->organic->getAdsConfig()->getPrebidBuildUrl() ); ?>");
                    <?php } ?>
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

        if ( $this->organic->useAdsOnPage() && $this->organic->useInjectedAdsConfig() ) {
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

        if ( $this->organic->useAdsOnPage() ) {
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

        if ( $this->organic->useAffiliateOnPage() ) {
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
            /*
                Various reusable utilities and debugging tools
            */
            var utils = {
                queryString: {},
                init: function() {
                    // parse and store the query string on init
                    var queryString = this.queryString;

                    location.search.slice(1).split('&').forEach(function(pair) {
                        pair = pair.split('=');
                        queryString[pair[0]] = decodeURIComponent(pair[1] || '');
                    });

                    // log CLS to console if ?debug_cls=true
                    if (queryString.debug_cls === 'true') {
                        this.logLayoutShift();
                    }
                },
                logLayoutShift: function() {
                    var totalShift = 0;

                    try {
                        function perfObserver(list) {
                            for (i = 0; i < list.getEntries().length; i++) {
                                var entry = list.getEntries()[i];

                                totalShift += entry.value;
                                console.log('Layout shift: ' + entry.value + '. CLS: ' + totalShift + '.');
                            }
                        }

                        var po = new PerformanceObserver(perfObserver);

                        po.observe({
                            type: 'layout-shift',
                            buffered: true
                        });
                    } catch (e) {
                        console.log('PerformanceObserver not supported.');
                    }
                },

                setCookie: function(cname, cvalue, exDays) {
                    var d = new Date();
                    var exTime = 2147483647;  // The maximum, can be overridden with exDays
                    var expires;
                    if (typeof exDays !== 'undefined') {
                        d.setTime(d.getTime() + (exDays*24*60*60*1000));
                        exTime = d.toUTCString();
                    }
                    expires = "expires=" + exTime;
                    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
                },

                getCookie: function(cname) {
                    var v = document.cookie.match('(^|;) ?' + cname + '=([^;]*)(;|$)');
                    return v ? v[2] : null;
                },

                deleteCookie: function(cname) {
                    utils.setCookie(cname, '', -1);
                },

                /**
                 * Load a script once.
                 *
                 * Use the id to ensure a script is only loaded once.
                 *
                 * @param id (str) The id for the script tag.
                 * @param src (str) The source for the script.
                 */
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

            window.BVTests = (function() {
                var tests = {};
                var userBuckets = {};
                var cookiePrefix = 'bv_test__';
                var debugParam = 'debug_bv_tests';
                var debugLogs = ('debug_tests' in utils.queryString);


                function _log() {
                    if (!debugLogs) {
                        return;
                    }
                    console.log.apply(null, arguments);
                }

                /**
                 * Create a split test with a given name, bucket the user.
                 *
                 * @param name (str) The test name.
                 * @param config (object) The test config.
                 *
                 * Config object contains bucket names and percentages. Control is not required and will
                 * be ignored. Control will automatically get the leftover values.
                 *
                 * Example:
                 *
                 *   BVTests.create('someTest', {
                 *     bucketA: 5
                 *   });
                 *
                 * In the above example, we create a test called "someTest" where control is 95% and
                 * bucketA is 5%;
                 */
                function create(name, config) {
                    if (tests[name]) {
                        return;
                    }

                    // If we're using a query string, force a bucket even if the bucket doesn't exist yet
                    // Debug param would be something like `debug_bv_tests=testName-testBucket,test2Name-testBucket`
                    var qsTests = utils.queryString[debugParam];
                    if (qsTests) {
                        qsTests = qsTests.split(',');
                        for (var i=0; i<qsTests.length; i++) {
                            var _qsTest = qsTests[i].split('-');
                            if (_qsTest.length === 2 && _qsTest[0] === name) {
                                userBuckets[name] = _qsTest[1];
                                utils.setCookie(cookiePrefix + name, _qsTest[1]);
                                _log('User bucketed from query string param:', name, _qsTest[1]);
                                return;
                            }
                        }
                    }

                    // If the user is already cookied with a valid bucket, use it
                    var cookiedBucket = utils.getCookie(cookiePrefix + name);
                    if (cookiedBucket && (cookiedBucket === 'control' || cookiedBucket in config)) {
                        userBuckets[name] = cookiedBucket;
                        _log('User bucketed from cookie:', name, cookiedBucket);
                        return;
                    }

                    tests[name] = config;
                    userBuckets[name] = 'control';  // Default to control

                    // Build the weighted bucket array
                    var weightedBuckets = [];
                    var rate;
                    for (var bucket in config) {
                        rate = parseInt(config[bucket]);
                        for (var i=0; i<rate; i++) {
                            weightedBuckets.push(bucket);
                        }
                    }

                    // Fill the remaining with "control"
                    var total = weightedBuckets.length;
                    if (total < 100) {
                        for (var i=0; i<(100-total); i++) {
                            weightedBuckets.push('control');
                        }
                    }

                    _log('weightedBuckets', weightedBuckets.length, weightedBuckets);

                    // Choose a bucket at random
                    var selected = weightedBuckets[Math.floor(Math.random() * weightedBuckets.length)];
                    _log('user sampled:', rate, name, selected);
                    userBuckets[name] = selected;

                    // Set the users bucket as a cookie
                    utils.setCookie(cookiePrefix + name, userBuckets[name]);
                    _log('user bucketed:', name, userBuckets[name]);
                }

                /**
                 * Build the targeting string based on tests created.
                 *
                 * Example: testName-testBucket,test2Name-testBucket
                 *
                 * TODO: Add validation on name and value. Cannot contain '-' or ','.
                 */
                function getTargetingValue() {
                    var targeting = [];
                    for (var name in userBuckets) {
                        var value = userBuckets[name];
                        targeting.push(name + '-' + value);
                    }
                    return targeting;
                }

                return {
                    create: create,
                    getValue: function(name) { return userBuckets[name]; },
                    getUserBuckets: function() { return userBuckets; },
                    getTargetingValue: getTargetingValue
                }
            })();
        </script>
        <?php
    }

    /**
     * Adds in media URLs to the RSS feed to allow outstream players to rely on the feed for slideshows (for Connatix)
     *
     * @return void
     */
    public function injectRssImage() {
        if ( has_post_thumbnail() ) {
            echo '<media:content url="' . esc_url( get_the_post_thumbnail_url( null, 'medium' ) ) . '" medium="image" />';
        }
    }

    /**
     * Adds in support for media URLs via an RSS2 extension
     *
     * @return void
     */
    public function injectRssNs() {
        echo 'xmlns:media="http://search.yahoo.com/mrss/"';
    }
}
