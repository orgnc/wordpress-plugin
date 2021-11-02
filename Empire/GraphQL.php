<?php


namespace Empire;

use \register_graphql_field, \register_graphql_object_type;


class GraphQL
{
    /**
     * @var Empire
     */
    protected $empire;

    public function __construct(Empire $empire)
    {
        $this->empire = $empire;

        add_action( 'plugins_loaded', [ $this, 'register_hooks' ] );
    }

    /**
     * Safely register hooks with the GraphQL infrastructure, if it is installed and activated.
     */
    public function register_hooks() {
        // We can only register with GraphQL if the CMS has GraphQL plugin enabled
        if ( !function_exists( '\register_graphql_object_type' ) ) {
            return;
        }

        \register_graphql_object_type( 'EmpireConfig', [
            'description' => __( 'Sitewide Configuration for Empire Platform', 'empireio' ),
            'fields' => [
                'adsEnabled' => [
                    'type' => 'Boolean',
                    'description' => __( 'Are we running with Empire Ads?', 'empireio' ),
                ],
                'adsTestEnabled' => [
                    'type' => 'Boolean',
                    'description' => __(
                        'If ads are enabled only for a subset of traffic',
                        'empireio'
                    ),
                ],
                'adsTestPercentEnabled' => [
                    'type' => 'Int',
                    'description' => __(
                        'If testing ads, what % of traffic is enabled?',
                        'empireio'
                    ),
                ],
                'adsTestSplitTestKey' => [
                    'type' => 'String',
                    'description' => __( 'If testing ads, key to send to GA and GAM', 'empireio' ),
                ],
                'adsTxt' => [
                    'type' => 'String',
                    'description' => __( 'Contents of ads.txt Managed by Empire', 'empireio' ),
                ],
                'ampAdsEnabled' => [
                    'type' => 'Boolean',
                    'description' => __( 'If true, show ads on AMP pages', 'empireio' ),
                ],
                'connatixPlayspaceEnabled' => [
                    'type' => 'Boolean',
                    'description' => __( 'Are we injecting an outstream player?', 'empireio' ),
                ],
                'connatixPlayspaceId' => [
                    'type' => 'Boolean',
                    'description' => __( 'Connatix Playspace ID, if set', 'empireio' ),
                ],
                'oneTrustEnabled' => [
                    'type' => 'Boolean',
                    'description' => __( 'Should we use OneTrust as our CMP?', 'empireio' ),
                ],
                'oneTrustSiteId' => [
                    'type' => 'String',
                    'description' => __( 'Site ID from OneTrust for the CMP config', 'empireio' ),
                ],
                'preloadConfigEnabled' => [
                    'type' => 'Boolean',
                    'description' => __( 'If true, preload config JSON in site code', 'empireio' ),
                ],
                'preloadConfigRules' => [
                    'type' => 'String',
                    'description' => __( 'JSON of the display (blocking) rules', 'empireio' )
                ],
                'preloadContainersEnabled' => [
                    'type' => 'Boolean',
                    'description' => __(
                        'If true, include pre-sized divs in the DOM for optimal CLS scores',
                        'empireio'
                    ),
                ],
                'preloadContainersConfig' => [
                    'type' => 'String',
                    'description' => __( 'JSON of the placement rules', 'empireio' )
                ],
                'siteId' => [
                    'type' => 'String',
                    'description' => __( 'Site ID for this site within Empire', 'empireio' ),
                ],
            ],
        ] );

        add_action( 'graphql_register_types', [$this, 'global_ads_config'] );
    }

    public function global_ads_config() {
        \register_graphql_field( 'RootQuery', 'empireConfig', [
            'type' => 'EmpireConfig',
            'description' => __( 'Sitewide Configuration for Empire Platform', 'empireio' ),
            'resolve' => function() {
                $testEnabled = $this->empire->getEmpirePixelTestPercent() < 100 &&
                    $this->empire->getEmpirePixelTestPercent() > 0;
                return [
                    'adsEnabled' => $this->empire->isEnabled(),
                    'adsTestEnabled' => $testEnabled,
                    'adsTestPercentEnabled' => $testEnabled ?
                        $this->empire->getEmpirePixelTestPercent() : null,
                    'adsTestSplitTestKey' => $testEnabled ?
                        $this->empire->getEmpirePixelTestValue() : null,
                    'adsTxt' => $this->empire->getAdsTxtManager()->get(),
                    'ampAdsEnabled' => $this->empire->useAmpAds(),
                    'connatixPlayspaceEnabled' => $this->empire->useConnatix(),
                    'connatixPlayspaceId' => $this->empire->useConnatix() ?
                        $this->empire->getConnatixPlayspaceId() : null,
                    'oneTrustEnabled' => $this->empire->useCmpOneTrust(),
                    'oneTrustSiteId' => $this->empire->getOneTrustId(),
                    'preloadConfigEnabled' => $this->empire->useInjectedAdsConfig(),
                    'preloadConfigRules' => $this->empire->getAdsConfig()->adRules ?
                        json_encode($this->empire->getAdsConfig()->adRules) : '[]',
                    'preloadContainersEnabled' => $this->empire->useAdsSlotsPrefill(),
                    'preloadContainersConfig' => $this->empire->getAdsConfig()->forPlacement ?
                        json_encode($this->empire->getAdsConfig()->forPlacement) : '[]',
                    'siteId' => $this->empire->getSiteId(),
                ];
            }
        ] );

        \register_graphql_field( 'PostQuery', 'empireArticleId', [
            'type' => 'String',
            'description' => __( 'ArticleID for GAM for Revenue Attribution', 'empireio' ),
            'resolve' => function() {
                [
                    'keywords' => $keywords,
                    'category' => $category,
                    'gamPageId' => $gamPageId,
                    'gamExternalId' => $gamExternalId,
                ] = $this->empire->getTargeting();

                return $gamPageId;
            }
        ] );
    }
}
