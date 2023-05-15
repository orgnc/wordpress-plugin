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
        add_action( 'graphql_register_types', [ $this, 'register_organic_types' ] );
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
                    'type' => [ 'non_null' => 'String' ],
                    'description' => $this->organic->t( 'URL to load Organic SDK from', 'organic' ),
                ],
                'sdkUrlModule' => [
                    'type' => [ 'non_null' => 'String' ],
                    'description' => $this->organic->t( 'URL to load Organic SDK js-module from', 'organic' ),
                ],
                'sdkCustomCSSUrl' => [
                    'type' => [ 'non_null' => 'String' ],
                    'description' => $this->organic->t( 'URL to load Organic Custom CSS from', 'organic' ),
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
                    'description' => $this->organic->t( 'URL to load Organic Prebid from', 'organic' ),
                ],
                'adsPrebidUrlModule' => [
                    'type' => [ 'non_null' => 'String' ],
                    'description' => $this->organic->t( 'URL to load Organic Prebid jsmodule-build from', 'organic' ),
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
            ],
        ];
    }

    public function register_organic_types() {
        \register_graphql_field(
            'RootQuery',
            'organicConfig',
            [
                'type' => 'OrganicConfig',
                'description' => __( 'Sitewide Configuration for Organic Platform', 'organic' ),
                'resolve' => function() {
                    $organic = $this->organic;
                    $adsConfig = $organic->getAdsConfig();
                    $testEnabled = $organic->useSplitTest();
                    return [
                        'organicEnabled' => $organic->isEnabledAndConfigured(),
                        'sdkVersion' => $organic->getSdkVersion(),
                        'siteDomain' => $organic->siteDomain,
                        'siteId' => $organic->getSiteId(),
                        'sdkUrl' => $organic->getSdkUrl(),
                        'sdkUrlModule' => $organic->getSdkUrl( 'module' ),
                        'sdkCustomCSSUrl' => $organic->getCustomCSSUrl(),
                        'oneTrustEnabled' => $organic->useCmpOneTrust(),
                        'oneTrustSiteId' => $organic->getOneTrustId(),
                        'adsEnabled' => $organic->useAds(),
                        'adsRawData' => $adsConfig->raw
                            ? json_encode( $adsConfig->raw )
                            : null,
                        'adsPrebidUrl' => $adsConfig->getPrebidBuildUrl(),
                        'adsPrebidUrlModule' => $adsConfig->getPrebidBuildUrl( 'module' ),
                        'affiliateEnabled' => $organic->useAffiliate(),
                        'splitTestEnabled' => $testEnabled,
                        'splitTestPercent' => $testEnabled
                            ? $organic->getSplitTestPercent()
                            : null,
                        'splitTestKey' => $testEnabled
                            ? $organic->getSplitTestKey()
                            : null,
                        'ampEnabled' => $organic->useAmp(),
                        'prefillEnabled' => $organic->usePrefill(),
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
