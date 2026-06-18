<?php

namespace Organic\SDK;

use GraphQL\Client;
use GraphQL\Exception\QueryError;
use GraphQL\Mutation;
use GraphQL\Variable;
use GraphQL\Query;
use GraphQL\RawObject;
use GuzzleHttp\Client as RestClient; // let's switch to GraphQL in the future
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use RuntimeException;


/**
 * Communicate with the Organic Platform APIs (GraphQL)
 *
 * @package Organic\SDK
 */
class OrganicSdk {

    const DEFAULT_API_URL = 'https://api.organic.ly/graphql';
    const DEFAULT_ASSETS_URL = 'https://organiccdn.io/assets/';
    const FALLBACK_PREBID_BUILD = 'sdk/prebid-stable.js';
    const SDK_V2 = 'v2';

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $cdnUrl;

    /**
     * @var Client GraphQL Client
     */
    private $client;

    /**
     * @var string GUID for the site we are working on
     */
    private $siteGuid;

    /**
     * Set up everything we need to work with the Organic API in the context of a single site.
     *
     * @param string $siteGuid
     * @param string|null $token
     * @param string|null $apiUrl
     * @param string|null $cdnUrl
     */
    public function __construct(
        string $siteGuid,
               $token = null,
               $apiUrl = null,
               $cdnUrl = null
    ) {
        if ( ! $apiUrl ) {
            $apiUrl = self::DEFAULT_API_URL;
        }
        $this->apiUrl = $apiUrl;

        if ( ! $cdnUrl ) {
            $cdnUrl = self::DEFAULT_ASSETS_URL;
        }
        $this->cdnUrl = $cdnUrl;

        $params = [];
        if ( $token ) {
            $params['x-api-key'] = $token;
        }

        $this->client = new Client(
            $apiUrl,
            $params
        );
        $this->siteGuid = $siteGuid;
    }

    public function getAPIUrl() {
        return $this->apiUrl;
    }

    public function getCDNUrl() {
        return $this->cdnUrl;
    }


    /**
     * Builds the SDK V2 URL to embed the JS SDK into web pages
     *
     * @return string
     */
    public function getSdkV2Url( string $type ) {
        $default = $this->cdnUrl . 'sdk/sdkv2?guid=' . $this->siteGuid;
        if ( $type === 'module' ) {
            return $default . '&usemodules=true';
        }

        return $default;
    }

    /**
     * Builds fallback prebid.js URL
     *
     * @return string
     */
    public function getFallbackPrebidBuildUrl() {
        return $this->cdnUrl . self::FALLBACK_PREBID_BUILD;
    }

    public function queryAdConfig() {
        $gql = ( new Query( 'appAds' ) );
        $gql->setArguments(
            [
                'siteGuids' => [ $this->siteGuid ],
            ]
        );
        $gql->setSelectionSet(
            [
                ( new Query( 'sites' ) )->setSelectionSet(
                    [
                        'domain',
                        ( new Query( 'settings' ) )->setSelectionSet(
                            [
                                ( new Query( 'adSettings' ) )->setSelectionSet(
                                    [
                                        'enableRefresh',
                                        'adsRefreshRate',
                                        'tabletBreakpointMin',
                                        'desktopBreakpointMin',
                                        ( new Query( 'amazon' ) )->setSelectionSet(
                                            [
                                                'enabled',
                                                'deals',
                                                'pubId',
                                            ]
                                        ),
                                        ( new Query( 'audigent' ) )->setSelectionSet(
                                            [
                                                'partnerId',
                                                'tagEnabled',
                                                'gamEnabled',
                                            ]
                                        ),
                                        ( new Query( 'browsi' ) )->setSelectionSet(
                                            [
                                                'enabled',
                                                'browsiId',
                                            ]
                                        ),
                                        ( new Query( 'outbrain' ) )->setSelectionSet(
                                            [
                                                'enabled',
                                                'selectors',
                                                'relative',
                                            ]
                                        ),
                                        ( new Query( 'nonRefresh' ) )->setSelectionSet(
                                            [
                                                'advertiserIds',
                                                'lineitemIds',
                                            ]
                                        ),
                                        ( new Query( 'lazyload' ) )->setSelectionSet(
                                            [
                                                'marginMobile',
                                                'marginDesktop',
                                            ]
                                        ),
                                        ( new Query( 'adpulse' ) )->setSelectionSet(
                                            [
                                                'enabled',
                                            ]
                                        ),
                                        ( new Query( 'consent' ) )->setSelectionSet(
                                            [
                                                'gdpr',
                                                'ccpa',
                                            ]
                                        ),
                                        'pixelSettings',
                                    ]
                                ),
                                ( new Query( 'adRules' ) )->setSelectionSet(
                                    [
                                        'guid',
                                        'component',
                                        'comparator',
                                        'value',
                                        'enabled',
                                        'placementKeys',
                                    ]
                                ),
                                ( new Query( 'placements' ) )->setSelectionSet(
                                    [
                                        'guid',
                                        'key',
                                        'name',
                                        'description',
                                        'adType',
                                        'connatixId',
                                        'adUnitId',
                                        ( new Query( 'relativeSelectors' ) )->setSelectionSet(
                                            [
                                                'relative',
                                                'selector',
                                            ]
                                        ),
                                        'relativeSettings',
                                        'prefillContainerCssClass',
                                        'limit',
                                        'isOutOfPage',
                                        'sizes',
                                        'pinHeightTo',
                                        'css',
                                        'prefillDisabled',
                                        'slotSize',
                                        'slotAspectRatio',
                                        'customTargeting',
                                        'enabled',
                                        'desktopEnabled',
                                        'tabletEnabled',
                                        'mobileEnabled',
                                        'lazyloadEnabled',
                                        'refreshEnabled',
                                        'refreshStrategy',
                                        'disablePrebid',
                                        'disableAmazon',
                                        'indicatorEnabled',
                                        ( new Query( 'indicatorSettings' ) )->setSelectionSet(
                                            [
                                                'topCaption',
                                                'bottomCaption',
                                                'topDivider',
                                                'bottomDivider',
                                                'captionColor',
                                                'dividerColor',
                                            ]
                                        ),
                                    ]
                                ),
                                ( new Query( 'prebid' ) )->setSelectionSet(
                                    [
                                        'enabled',
                                        'timeout',
                                        'useBuild',
                                        ( new Query( 'bidders' ) )->setSelectionSet(
                                            [
                                                'key',
                                                'name',
                                                'enabled',
                                                'placementSettings',
                                                'bidAssignment',
                                                'bidCpmAdjustment',
                                            ]
                                        ),
                                    ]
                                ),
                            ]
                        ),
                        ( new Query( 'ampConfig' ) )->setSelectionSet(
                            [
                                ( new Query( 'placements' ) )->setSelectionSet(
                                    [
                                        'key',
                                        'html',
                                    ]
                                ),
                                'requiredScripts',
                            ]
                        ),
                        ( new Query( 'prefillConfig' ) )->setSelectionSet(
                            [
                                ( new Query( 'placements' ) )->setSelectionSet(
                                    [
                                        'key',
                                        'html',
                                        'css',
                                    ]
                                ),
                            ]
                        ),
                    ]
                ),
            ]
        );
        $result = $this->runQuery( $gql );
        return $result['data']['appAds']['sites'][0];
    }

    public function queryAdsRefreshRates() {
        $gql = new Query( 'adsRefreshRates' );
        $gql->setArguments(
            [
                'siteGuids' => [ $this->siteGuid ],
            ]
        );
        $gql->setSelectionSet(
            [
                'guid',
                'targetType',
                'targetGuid',
                'value',
                ( new Query( 'restrictions' ) )->setSelectionSet(
                    [
                        ( new Query( 'devices' ) )->setSelectionSet(
                            [
                                'deviceType',
                                'os',
                            ]
                        ),
                        ( new Query( 'timeRanges' ) )->setSelectionSet(
                            [
                                'start',
                                'end',
                            ]
                        ),
                        ( new Query( 'placements' ) )->setSelectionSet(
                            [
                                'guid',
                                'name',
                                'key',
                            ]
                        ),
                    ]
                ),
            ]
        );
        $result = $this->runQuery( $gql );
        return $result['data']['adsRefreshRates'];
    }

    public function queryAdsTxt(): string {
        $gql = ( new Query( 'adsTxt' ) );
        $gql->setArguments(
            [
                'siteGuid' => $this->siteGuid,
            ]
        );
        $gql->setSelectionSet(
            [
                'text',
            ]
        );
        $result = $this->runQuery( $gql );
        return $result['data']['adsTxt']['text'];
    }

    /**
     * @throws \Exception|GuzzleException
     */
    public function queryAffiliateConfig() {
        // make a call to platform API to get affiliate config
        $site_guid = $this->siteGuid;
        $api_url = 'https://api.organic.ly/sdkv2/config/' . $site_guid;
        $client = new RestClient();
        $response = $client->get( $api_url );
        $json = json_decode( $response->getBody(), true );
        $guid = $json['affiliateConfig']['siteConf']['guid'];
        if ( $guid !== $site_guid ) {
            throw new \Exception( 'Could not verify affiliate site guid' );
        }
        return $json['affiliateConfig']['siteConf'];
    }

    /**
     * Run a mutation on the platform API to save important WordPress settings platform-side,
     * then query the response to save important platform settings here on the WordPress side.
     *
     * @throws RuntimeException
     */
    public function mutateAndQueryWordPressConfig( \Organic\Organic $organic ) {
        global $wp_version;
        $mutation = new Mutation( 'syncWordpressPluginConfig' );
        $mutation->setVariables( [ new Variable( 'configInput', 'WordpressPluginConfigInput', true ) ] );
        $mutation->setArguments( [ 'configInput' => '$configInput' ] );
        $mutation->setSelectionSet(
            [ ( new Query( 'config' ) )->setSelectionSet( [ 'sentryDsn' ] ) ]
        );

        # Note that this might be true even before the site is fully migrated.
        $organicContentEnabled = false;
        if (
            class_exists( '\SWPCore\HeadlessFrontend' ) &&
            method_exists( '\SWPCore\HeadlessFrontend', 'isAvailable' )
        ) {
            $organicContentEnabled = \SWPCore\HeadlessFrontend::isAvailable();
        }

        $variables = [
            'configInput' => [
                'siteGuid' => $this->siteGuid,
                'organicIntegrationEnabled' => $organic->isEnabled(),
                'organicContentEnabled' => $organicContentEnabled,
                'phpVersion' => phpversion(),
                'wordpressVersion' => $wp_version,
                'pluginVersion' => $organic->version,
                'sdkVersion' => $organic->getSdkVersion(),
                'adsTxtRedirectEnabled' => $organic->adsTxtRedirectionEnabled(),
                'splitTestEnabled' => $organic->useSplitTest(),
                'consentManagementPlatform' => $organic->getCmp(),
                'pluginSettingsLastUpdated' => $organic->settingsLastUpdated()->format( 'c' ),
            ],
        ];

        $result = $this->runQuery( $mutation, $variables );
        return $result['data']['syncWordpressPluginConfig']['config'];
    }

    /**
     * Helper for standardized error handling when running a GraphQL query or mutation
     *
     * @param $query
     * @param array $variables
     * @return array|object
     * @throws RuntimeException if API returns a failure code
     */
    private function runQuery( $query, array $variables = [] ) {
        try {
            $result = $this->client->runQuery( $query, true, $variables );
            $responseCode = $result->getResponseObject()->getStatusCode();
            if ( $responseCode > 201 ) {
                throw new RuntimeException( 'Organic API Failed with Error Code ' . $responseCode );
            }

            return $result->getResults();
        } catch ( QueryError $e ) {
            // Variable is encoded this way so we get more context in Sentry
            $query_error_details = json_encode( $e->getErrorDetails() );
            throw new RuntimeException( 'Organic API Failed with ' . count( json_decode( $query_error_details ) . ' errors' ), -1, $e );
        }
    }

    /**
     * Update the GraphQL client to use a new token
     *
     * @param string|null $token
     */
    public function updateToken( $token ) {
        $params = [];
        if ( $token ) {
            $params['x-api-key'] = $token;
        }
        $this->client = new Client(
            $this->apiUrl,
            $params
        );
    }

    public function queryAssets() {
        $assets = [];
        $first = 50;
        $skip = 0;
        do {
            $gql = ( new Query( 'appCampaigns' ) );
            $gql->setSelectionSet(
                [
                    ( new Query( 'assets' ) )->setArguments(
                        [
                            'channel' => ( new RawObject( 'CONTENT' ) ),
                            'first' => $first,
                            'skip' => $skip,
                            'siteGuids' => [ $this->siteGuid ],
                        ]
                    )->setSelectionSet(
                        [
                            ( new Query( 'edges' ) )->setSelectionSet(
                                [
                                    ( new Query( 'node' ) )->setSelectionSet(
                                        [
                                            'guid',
                                            'name',
                                            'externalId',
                                            'startDate',
                                            'endDate',
                                            ( new Query( 'campaign' ) )->setSelectionSet(
                                                [
                                                    'id',
                                                    'guid',
                                                    'status',
                                                    'name',
                                                ]
                                            ),
                                        ]
                                    ),
                                ]
                            ),
                            ( new Query( 'pageInfo' ) )->setSelectionSet(
                                [
                                    'totalObjects',
                                ]
                            ),
                        ]
                    ),
                ]
            );
            $result = $this->runQuery( $gql );
            $page_data = $result['data']['appCampaigns']['assets'];
            $total_objects = $page_data['pageInfo']['totalObjects'];
            $skip += $first;

            foreach ( $page_data['edges'] as $node ) {
                $assets[] = $node['node'];
            }
            $loaded = count( $assets );
        } while ( $loaded < $total_objects );

        return $assets;
    }
}
