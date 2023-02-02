<?php


namespace Organic;

use \register_graphql_field, \register_graphql_object_type;

class GraphQL {

    /**
     * @var Organic
     */
    protected $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
    }

    /**
     * Initialization to run when using within Wordpress context
     */
    public function init() {
        add_action( 'plugins_loaded', [ $this, 'register_hooks' ] );
    }

    /**
     * Safely register hooks with the GraphQL infrastructure, if it is installed and activated.
     */
    public function register_hooks() {
        // We can only register with GraphQL if the CMS has GraphQL plugin enabled
        if ( ! function_exists( 'register_graphql_object_type' ) ) {
            return;
        }

        register_graphql_object_type( 'OrganicConfig', self::getGraphQLSpec() );
        add_action( 'graphql_register_types', [ $this, 'global_ads_config' ] );
    }

    /**
     * @return array
     */
    public function getGraphQLSpec() {
        return [
            'description' => $this->organic->t( 'Sitewide Configuration for Organic Platform', 'organic' ),
            'fields' => [
                'organicEnabled' => [
                    'type' => [ 'non_null' => 'Boolean' ],
                    'description' => $this->organic->t( 'Organic sync and SDK are enabled', 'organic' ),
                ],
                'sdkVersion' => [
                    'type' => [ 'non_null' => 'String' ],
                    'description' => $this->organic->t( 'SDK version', 'organic' ),
                ],
                'siteDomain' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'Site domain for this site within Organic', 'organic' ),
                ],
                'siteId' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'Site ID for this site within Organic', 'organic' ),
                ],
                'sdkUrl' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'URL to load Organic SDK from', 'organic' ),
                ],
                'oneTrustEnabled' => [
                    'type' => [ 'non_null' => 'Boolean' ],
                    'description' => $this->organic->t( 'Should we use OneTrust as our CMP?', 'organic' ),
                ],
                'oneTrustSiteId' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'Site ID from OneTrust for the CMP config', 'organic' ),
                ],
                'adsEnabled' => [
                    'type' => [ 'non_null' => 'Boolean' ],
                    'description' => $this->organic->t( 'Are we using Organic Ads?', 'organic' ),
                ],
                'adsRawData' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'JSON of the raw Ads rules', 'organic' ),
                ],
                'adsPrebidUrl' => [
                    'type' => [ 'non_null' => 'String' ],
                    'description' => $this->organic->t( 'URL to load Organic SDK from', 'organic' ),
                ],
                'affiliateEnabled' => [
                    'type' => [ 'non_null' => 'Boolean' ],
                    'description' => $this->organic->t( 'Are we using Organic Affiliate?', 'organic' ),
                ],
                'splitTestEnabled' => [
                    'type' => [ 'non_null' => 'Boolean' ],
                    'description' => $this->organic->t(
                        'If Organic SDK is enabled only for a subset of traffic',
                        'organic'
                    ),
                ],
                'splitTestPercent' => [
                    'type' => 'Int',
                    'description' => $this->organic->t(
                        'If testing Organic SDK, what % of traffic is enabled?',
                        'organic'
                    ),
                ],
                'splitTestKey' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'If testing ads, key to send to GA and GAM', 'organic' ),
                ],
                'ampEnabled' => [
                    'type' => [ 'non_null' => 'Boolean' ],
                    'description' => $this->organic->t( 'If true, show ads on AMP pages', 'organic' ),
                ],
                'prefillEnabled' => [
                    'type' => [ 'non_null' => 'Boolean' ],
                    'description' => $this->organic->t(
                        'If true, include pre-sized divs in the DOM for optimal CLS scores',
                        'organic'
                    ),
                ],

                'adsTestEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t(
                        '[DEPRECATED] If ads are enabled only for a subset of traffic',
                        'organic'
                    ),
                ],
                'adsTestPercentEnabled' => [
                    'type' => 'Int',
                    'description' => $this->organic->t(
                        '[DEPRECATED] If testing ads, what % of traffic is enabled?',
                        'organic'
                    ),
                ],
                'adsTestSplitTestKey' => [
                    'type' => 'String',
                    'description' => $this->organic->t( '[DEPRECATED] If testing ads, key to send to GA and GAM', 'organic' ),
                ],
                'adsTxt' => [
                    'type' => 'String',
                    'description' => $this->organic->t( '[DEPRECATED] Contents of ads.txt Managed by Organic Ads', 'organic' ),
                ],
                'ampAdsEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t( '[DEPRECATED] If true, show ads on AMP pages', 'organic' ),
                ],
                'preloadConfigEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t( '[DEPRECATED] If true, preload config JSON in site code', 'organic' ),
                ],
                'preloadConfigRules' => [
                    'type' => 'String',
                    'description' => $this->organic->t( '[DEPRECATED] JSON of the display (blocking) rules', 'organic' ),
                ],
                'preloadContainersEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t(
                        '[DEPRECATED] If true, include pre-sized divs in the DOM for optimal CLS scores',
                        'organic'
                    ),
                ],
                'preloadContainersConfig' => [
                    'type' => 'String',
                    'description' => $this->organic->t( '[DEPRECATED] JSON of the placement rules', 'organic' ),
                ],
            ],
        ];
    }

    public function global_ads_config() {
        \register_graphql_field(
            'RootQuery',
            'organicConfig',
            [
                'type' => 'OrganicConfig',
                'description' => __( 'Sitewide Configuration for Organic Platform', 'organic' ),
                'resolve' => function() {
                    $testEnabled = $this->organic->useSplitTest();
                    return [
                        'organicEnabled' => $this->organic->isEnabledAndConfigured(),
                        'sdkVersion' => $this->organic->getSdkVersion(),
                        'siteDomain' => $this->organic->siteDomain,
                        'siteId' => $this->organic->getSiteId(),
                        'sdkUrl' => $this->organic->getSdkUrl(),
                        'oneTrustEnabled' => $this->organic->useCmpOneTrust(),
                        'oneTrustSiteId' => $this->organic->getOneTrustId(),
                        'adsEnabled' => $this->organic->useAds(),
                        'adsRawData' => $this->organic->getAdsConfig()->raw
                            ? json_encode( $this->organic->getAdsConfig()->raw )
                            : null,
                        'adsPrebidUrl' => $this->organic->getAdsConfig()->getPrebidBuildUrl(),
                        'affiliateEnabled' => $this->organic->useAffiliate(),
                        'splitTestEnabled' => $testEnabled,
                        'splitTestPercent' => $testEnabled
                            ? $this->organic->getSplitTestPercent()
                            : null,
                        'splitTestKey' => $testEnabled
                            ? $this->organic->getSplitTestKey()
                            : null,
                        'ampEnabled' => $this->organic->useAmp(),
                        'prefillEnabled' => $this->organic->usePrefill(),

                        'adsTestEnabled' => $testEnabled,
                        'adsTestPercentEnabled' => $testEnabled
                            ? $this->organic->getSplitTestPercent()
                            : null,
                        'adsTestSplitTestKey' => $testEnabled
                            ? $this->organic->getSplitTestKey()
                            : null,
                        'adsTxt' => $this->organic->getAdsTxtManager()->get(),
                        'ampAdsEnabled' => $this->organic->useAmp(),
                        'preloadConfigEnabled' => $this->organic->useInjectedAdsConfig(),
                        'preloadConfigRules' => $this->organic->getAdsConfig()->adRules
                            ? json_encode( $this->organic->getAdsConfig()->adRules )
                            : '[]',
                        'preloadContainersEnabled' => $this->organic->usePrefill(),
                        'preloadContainersConfig' => $this->organic->getAdsConfig()->forPlacement
                            ? json_encode( $this->organic->getAdsConfig()->forPlacement )
                            : '[]',
                    ];
                },
            ]
        );

        \register_graphql_field(
            'Post',
            'organicArticleId',
            [
                'type' => 'String',
                'description' => __( 'ArticleID for GAM for Revenue Attribution', 'organic' ),
                'resolve' => function() {
                    $targeting = $this->organic->getTargeting();
                    $gamPageId = $targeting['gamPageId'];
                    return $gamPageId;
                },
            ]
        );

        \register_graphql_field(
            'Post',
            'organicSiteId',
            [
                'type' => 'String',
                'description' => __( 'SiteID for Loading Organic SDK Instance', 'organic' ),
                'resolve' => function() {
                    return $this->organic->getSiteId();
                },
            ]
        );
    }
}
