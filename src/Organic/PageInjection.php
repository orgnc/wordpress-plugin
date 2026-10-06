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
        add_action( 'admin_head', [ $this, 'injectBrowserSDKInAdmin' ] );

        if ( $this->organic->useFeedImages() ) {
            add_action( 'rss2_item', [ $this, 'injectRssImage' ] );
            add_action( 'rss2_ns', [ $this, 'injectRssNs' ] );
        }
    }

    public function setupAdsAmp() {
        if ( ! $this->organic->useAds() ) {
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
        if ( ! $this->organic->useAds() ) {
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

    public function injectBrowserSDKInAdmin() {
        // The only use case for SDK in admin are Affiliate blocks
        if ( ! $this->organic->useAffiliate() ) {
            return;
        }

        // "This function is defined on most admin pages, but not all."
        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }
        $pt = get_current_screen()->post_type;
        if ( ! in_array( $pt, $this->organic->getPostTypes() ) ) {
            return;
        }

        $this->injectCoreSetup();
        $this->injectAffiliateSetup();
        $this->injectScriptTag(
            'organic-sdk',
            $this->organic->getSdkUrl(),
            $this->organic->getSdkUrl( 'module' )
        );
    }

    public function injectBrowserSDK() {
        $this->injectPrefetchHeaders();
        $this->injectCoreSetup();
        if ( $this->organic->useAdsOnPage() ) {
            $this->injectAdsSetup();
        }
        if ( $this->organic->useAffiliateOnPage() ) {
            $this->injectAffiliateSetup();
        }

        if ( ! $this->organic->useSplitTest() ) {
            // If we are not running split test then we need to be loading up our ad stack as quickly as possible,
            // which means that we should do it with <script> and <link> tags directly.
            $this->injectCustomCssTag();
            if ( $this->organic->useAdsOnPage() ) {
                $this->injectScriptTag(
                    'gpt',
                    'https://securepubads.g.doubleclick.net/tag/js/gpt.js'
                );
                $this->injectScriptTag(
                    'organic-prebid',
                    $this->organic->getAdsConfig()->getPrebidBuildUrl(),
                    $this->organic->getAdsConfig()->getPrebidBuildUrl( 'module' )
                );
            }
            $this->injectScriptTag(
                'organic-sdk',
                $this->organic->getSdkUrl(),
                $this->organic->getSdkUrl( 'module' )
            );
        } else {
            $this->injectSplitTestUtils();
            ?>
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <script id="organic-splittest-run">
                (function (){
                    var splitTest = window.organic.splitTest;
                    var splitTestKey = "<?php echo esc_js( $this->organic->getSplitTestKey() ); ?>";
                    splitTest.create(splitTestKey, {
                        enabled: <?php echo esc_js( $this->organic->getSplitTestPercent() ); ?>,
                    });

                    if ( splitTest.getValue(splitTestKey) === 'control' ) {
                        // Do nothing here, but rely on third party code to detect the use case and load the ads their way
                        return;
                    }

                    splitTest.loadCSS('organic-css',
                        "<?php echo esc_url_raw( $this->organic->getCustomCSSUrl() ); ?>",
                    );
                    <?php if ( $this->organic->useAdsOnPage() ) { ?>
                    splitTest.loadScript('gpt', 'https://securepubads.g.doubleclick.net/tag/js/gpt.js');
                    splitTest.loadScript('organic-prebid',
                        "<?php echo esc_url_raw( $this->organic->getAdsConfig()->getPrebidBuildUrl() ); ?>",
                        "<?php echo esc_url_raw( $this->organic->getAdsConfig()->getPrebidBuildUrl( 'module' ) ); ?>",
                    );
                    <?php } ?>
                    splitTest.loadScript('organic-sdk',
                        "<?php echo esc_url_raw( $this->organic->getSdkUrl() ); ?>",
                        "<?php echo esc_url_raw( $this->organic->getSdkUrl( 'module' ) ); ?>",
                    );
                })();
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

    public function injectCustomCssTag() {
        $cssUrl = $this->organic->getCustomCSSUrl();
        ?>
        <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
        <link id="organic-css" rel="stylesheet" href="<?php echo esc_url_raw( $cssUrl ); ?>" type="text/css" media="all" />
        <?php
    }

    public function injectCoreSetup() {
        ?>
        <script id="organic-sdk-core-setup">
            window.__organic_usp_cookie = 'ne-opt-out';
            window.organic = window.organic || {};
            window.organic.cmd = window.organic.cmd || [];
            window.organic.disableSDKAutoInitialization = true;

            // Core SDK is always enabled in SDKv2 (for SDKv1 it will be just undefined)
            window.organic.cmd.push(function(apps) {
                if (!apps.core) return;
                apps.core.init();
            });
        </script>
        <?php
    }

    public function injectAdsSetup() {
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
        <script id="organic-sdk-ads-setup">
            window.organic.cmd.push(function(apps) {
                var ads = apps.ads;
                if (!ads || !ads.isEnabled()) return;

                function getSplitTestValue() {
                    var organic = window.organic || {};
                    if (!organic.splitTest) return;
                    if (!((typeof organic.splitTest.getTargetingValue) == 'function')) return;

                    return organic.splitTest.getTargetingValue();
                }

                ads.init();
                ads.setTargeting({
                    pageId: '<?php echo esc_js( $gamPageId ); ?>',
                    externalId: '<?php echo esc_js( $gamExternalId ); ?>',
                    keywords: '<?php echo esc_js( $keywordString ); ?>',
                    disableKeywordReporting: false,
                    section: '<?php echo esc_js( $sectionString ); ?>',
                    disableSectionReporting: false,
                    tests: getSplitTestValue(),
                });
                ads.waitForPageLoad(function(){
                    ads.initializeAds();
                });
            });
        </script>
        <?php
    }

    public function injectAffiliateSetup() {
        ?>
        <script id="organic-sdk-affiliate-setup">
            window.organic.cmd.push(function(apps) {
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

    public function injectSplitTestUtils() {
        ?>
        <script id="organic-splittest-utils">
            window.organic = window.organic || {};
            window.organic.splitTest = (function() {
                var _tests = {};
                var _userBuckets = {};
                var _cookiePrefix = 'organic_splittest__';
                var _debugParam = 'organic-splittest-debug';
                var _queryString = (function() {
                    var query = {};

                    location.search.slice(1).split('&').forEach(function(pair) {
                        pair = pair.split('=');
                        query[pair[0]] = decodeURIComponent(pair[1] || '');
                    });
                    return query;
                })();
                var _debugLogs = ('organic-splittest-logs' in _queryString);

                function _log() {
                    if (!_debugLogs) return;
                    console.log.apply(null, arguments);
                }

                function _setCookie(cname, cvalue, exDays) {
                    var d = new Date();
                    var exTime = 2147483647;  // The maximum, can be overridden with exDays
                    var expires;
                    if (typeof exDays !== 'undefined') {
                        d.setTime(d.getTime() + (exDays*24*60*60*1000));
                        exTime = d.toUTCString();
                    }
                    expires = "expires=" + exTime;
                    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
                };

                function _getCookie(cname) {
                    var v = document.cookie.match('(^|;) ?' + cname + '=([^;]*)(;|$)');
                    return v ? v[2] : null;
                };

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
                 *   window.organic.splitTest.create('someTest', {
                 *     bucketA: 5
                 *   });
                 *
                 * In the above example, we create a test called "someTest" where control is 95% and
                 * bucketA is 5%;
                 */
                function create(name, config) {
                    if (_tests[name]) {
                        return;
                    }

                    // If we're using a query string, force a bucket even if the bucket doesn't exist yet
                    // Debug param would be something like `organic-debug-splittest=testName-testBucket,test2Name-testBucket`
                    var qsTests = _queryString[_debugParam];
                    if (qsTests) {
                        qsTests = qsTests.split(',');
                        for (var i=0; i<qsTests.length; i++) {
                            var qsTest = qsTests[i].split('-');
                            if (qsTest.length === 2 && qsTest[0] === name) {
                                _userBuckets[name] = qsTest[1];
                                _setCookie(_cookiePrefix + name, qsTest[1]);
                                _log('User bucketed from query string param:', name, qsTest[1]);
                                return;
                            }
                        }
                    }

                    // If the user is already cookied with a valid bucket, use it
                    var cookiedBucket = _getCookie(_cookiePrefix + name);
                    if (cookiedBucket && (cookiedBucket === 'control' || cookiedBucket in config)) {
                        _userBuckets[name] = cookiedBucket;
                        _log('User bucketed from cookie:', name, cookiedBucket);
                        return;
                    }

                    _tests[name] = config;
                    _userBuckets[name] = 'control';  // Default to control

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
                    _userBuckets[name] = selected;

                    // Set the users bucket as a cookie
                    _setCookie(_cookiePrefix + name, _userBuckets[name]);
                    _log('user bucketed:', name, _userBuckets[name]);
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
                    for (var name in _userBuckets) {
                        var value = _userBuckets[name];
                        targeting.push(name + '-' + value);
                    }
                    return targeting;
                }

                /**
                 * Load a script once.
                 *
                 * Use the id to ensure a script is only loaded once.
                 *
                 * @param id (str) The id for the script tag.
                 * @param src (str) The source for the script.
                 * @param moduleSrc (str) The source for the js-module script.
                 */
                function loadScript(id, src, moduleSrc) {
                    if (document.getElementById(id)) return;
                    var script = document.createElement('script');
                    script.id = id;
                    script.async = true;

                    var useModules = (typeof script.noModule === 'boolean') && moduleSrc;
                    if (useModules) {
                        // If js-module provided and browser supports it
                        script.type = 'module';
                        script.src = moduleSrc;
                    } else {
                        // Using regular script
                        script.src = src;
                    }
                    document.getElementsByTagName('head')[0].appendChild(script);
                }

                function loadCSS(id, src) {
                    if (document.getElementById(id)) return;
                    var link = document.createElement('link');
                    link.id = id;
                    link.rel = 'stylesheet';
                    link.type = 'text/css';
                    link.href = src;
                    document.getElementsByTagName('head')[0].appendChild(link);
                }

                return {
                    create: create,
                    getValue: function(name) { return _userBuckets[name]; },
                    getUserBuckets: function() { return _userBuckets; },
                    getTargetingValue: getTargetingValue,
                    loadScript: loadScript,
                    loadCSS: loadCSS
                }
            })();
        </script>
        <?php
    }

    public function injectScriptTag( $id, $src, $modulesSrc = null ) {
        if ( ! $modulesSrc ) {
            wp_print_script_tag(
                [
                    'id' => $id,
                    'src' => $src,
                    'async' => true,
                ]
            );
            return;
        }
        // The script to be used if modules are supported
        wp_print_script_tag(
            [
                'id' => $id . '-mjs',
                'src' => $modulesSrc,
                'async' => true,
                'type' => 'module',
            ]
        );
        // The script to be used if modules are not supported
        wp_print_script_tag(
            [
                'id' => $id,
                'src' => $src,
                'async' => true,
                'nomodule' => true,
            ]
        );
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
