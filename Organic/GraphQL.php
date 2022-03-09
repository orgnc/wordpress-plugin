<?php


namespace Organic;

use \register_graphql_field, \register_graphql_object_type;

class GraphQL {

    /**
     * @var Organic
     */
    protected Organic $organic;

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
                'adsEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t( 'Are we running with Organic Ads?', 'organic' ),
                ],
                'adsTestEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t(
                        'If ads are enabled only for a subset of traffic',
                        'organic'
                    ),
                ],
                'adsTestPercentEnabled' => [
                    'type' => 'Int',
                    'description' => $this->organic->t(
                        'If testing ads, what % of traffic is enabled?',
                        'organic'
                    ),
                ],
                'adsTestSplitTestKey' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'If testing ads, key to send to GA and GAM', 'organic' ),
                ],
                'adsTxt' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'Contents of ads.txt Managed by Organic Ads', 'organic' ),
                ],
                'ampAdsEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t( 'If true, show ads on AMP pages', 'organic' ),
                ],
                'connatixPlayspaceEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t( 'Are we injecting an outstream player?', 'organic' ),
                ],
                'connatixPlayspaceId' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t( 'Connatix Playspace ID, if set', 'organic' ),
                ],
                'oneTrustEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t( 'Should we use OneTrust as our CMP?', 'organic' ),
                ],
                'oneTrustSiteId' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'Site ID from OneTrust for the CMP config', 'organic' ),
                ],
                'preloadConfigEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t( 'If true, preload config JSON in site code', 'organic' ),
                ],
                'preloadConfigRules' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'JSON of the display (blocking) rules', 'organic' ),
                ],
                'preloadContainersEnabled' => [
                    'type' => 'Boolean',
                    'description' => $this->organic->t(
                        'If true, include pre-sized divs in the DOM for optimal CLS scores',
                        'organic'
                    ),
                ],
                'preloadContainersConfig' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'JSON of the placement rules', 'organic' ),
                ],
                'siteId' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'Site ID for this site within Organic', 'organic' ),
                ],
                'adsRawData' => [
                    'type' => 'String',
                    'description' => $this->organic->t( 'JSON of the raw Ads rules', 'organic' ),
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
                    $testEnabled = $this->organic->getOrganicPixelTestPercent() < 100 &&
                        $this->organic->getOrganicPixelTestPercent() > 0;
                    return [
                        'adsEnabled' => $this->organic->isEnabled(),
                        'adsTestEnabled' => $testEnabled,
                        'adsTestPercentEnabled' => $testEnabled ?
                            $this->organic->getOrganicPixelTestPercent() : null,
                        'adsTestSplitTestKey' => $testEnabled ?
                            $this->organic->getOrganicPixelTestValue() : null,
                        'adsTxt' => $this->organic->getAdsTxtManager()->get(),
                        'ampAdsEnabled' => $this->organic->useAmpAds(),
                        'connatixPlayspaceEnabled' => $this->organic->useConnatix(),
                        'connatixPlayspaceId' => $this->organic->useConnatix() ?
                            $this->organic->getConnatixPlayspaceId() : null,
                        'oneTrustEnabled' => $this->organic->useCmpOneTrust(),
                        'oneTrustSiteId' => $this->organic->getOneTrustId(),
                        'preloadConfigEnabled' => $this->organic->useInjectedAdsConfig(),
                        'preloadConfigRules' => $this->organic->getAdsConfig()->adRules ?
                            json_encode( $this->organic->getAdsConfig()->adRules ) : '[]',
                        'preloadContainersEnabled' => $this->organic->useAdsSlotsPrefill(),
                        'preloadContainersConfig' => $this->organic->getAdsConfig()->forPlacement ?
                            json_encode( $this->organic->getAdsConfig()->forPlacement ) : '[]',
                        'siteId' => $this->organic->getSiteId(),
                        'adsRawData'=>$this->organic->getAdsConfig()->raw ?
                            json_encode( $this->organic->getAdsConfig()->raw ) : '[]'
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
                    [
                        'keywords' => $keywords,
                        'category' => $category,
                        'gamPageId' => $gamPageId,
                        'gamExternalId' => $gamExternalId,
                    ] = $this->organic->getTargeting();

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
