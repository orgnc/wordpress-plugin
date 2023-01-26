<?php

namespace Organic\SDK;

use DateTime;
use DateTimeInterface;
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
    const FALLBACK_PREBID_BUILD = 'prebid5.13.0.js';
    const SDK_V1 = 'v1';
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

    /**
     * Registers or updates meta data about Authors on this site
     *
     * @param string $externalId Unique ID for the Author known to your CMS
     * @param string $name Displayable name of the author (not necessarily unique)
     * @return array|object
     */

    public function authorUpdate( string $externalId, string $name ) {
        return $this->metaUpdate( 'authorUpdate', $externalId, $name );
    }

    /**
     * Registers or updates meta data about Categories on this site
     *
     * @param string $externalId
     * @param string $name
     * @return array|object
     */
    public function categoryUpdate( string $externalId, string $name ) {
        return $this->metaUpdate( 'categoryUpdate', $externalId, $name );
    }

    /**
     * Registers the complete tree of categories
     *
     * Example input:
     *   array(
     *     'externalId' => 'external_id_1',
     *     'name' => 'Category level 0',
     *     'children' => [
     *       array(
     *         'externalId' => 'external_id_2',
     *         'name' => 'Category level 1',
     *       )
     *     ]
     *   )
     *
     * @param array $categoryTree A hierarchical tree of categories
     * @return array|object
     */
    public function categoryTreeUpdate( array $categoryTree ) {
        $mutation = ( new NestedArgsMutation( 'categoryUpdate' ) );
        $mutation->setArguments( $categoryTree );
        $mutation->setSelectionSet( [ 'ok' ] );

        return $this->runQuery( $mutation );
    }

    /**
     * Builds the SDK URL to embed the JS SDK into web pages
     *
     * @return string
     */
    public function getSdkUrl() {
        return $this->cdnUrl . 'sdk/unit-sdk.js?' . $this->siteGuid;
    }

    /**
     * Builds the SDK V2 URL to embed the JS SDK into web pages
     *
     * @return string
     */
    public function getSdkV2Url() {
        return $this->cdnUrl . 'sdk/sdkv2?guid=' . $this->siteGuid;
    }

    /**
     * Builds fallback prebid.js URL
     *
     * @return string
     */
    public function getFallbackPrebidBuildUrl() {
        return $this->cdnUrl . self::FALLBACK_PREBID_BUILD;
    }

    /**
     * Registers or updates meta data about Tags on this site
     *
     * @param string $externalId
     * @param string $name
     * @return array|object
     */
    public function tagUpdate( string $externalId, string $name ) {
        return $this->metaUpdate( 'tagUpdate', $externalId, $name );
    }

    /**
     * Shared helper for updating our basic content metadata (authors, categories, tags)
     *
     * @param string $mutationName
     * @param string $externalId
     * @param string $name
     * @return array|object
     */
    protected function metaUpdate( string $mutationName, string $externalId, string $name ) {
        $mutation = ( new Mutation( $mutationName ) );
        $mutation->setArguments(
            [
                'externalId' => $externalId,
                'name' => $name,
                'siteGuid' => $this->siteGuid,
            ]
        );
        $mutation->setSelectionSet( [ 'ok' ] );
        return $this->runQuery( $mutation );
    }

    /**
     * Registers or updates meta data about Articles and other content on this site
     *
     * Some posts may contain only meta data, such as a specialized template or
     * be intentionally blank for use with partners that dynamically build in
     * content like Nativo. So, content can be empty for sure. Title probably
     * shouldn’t be blank and URL definitely can’t be blank.
     *
     * @param string $externalId Unique ID for this content on this site
     * @param string $canonicalUrl
     * @param string $title
     * @param string $subtitle
     * @param string $featured_image_url
     * @param string $template_name
     * @param string $sponsorship
     * @param string $is_published
     * @param DateTime $publishedDate
     * @param DateTime $modifiedDate
     * @param string $content
     * @param array $authors
     * @param array $categories
     * @param array $tags
     * @param array $third_party_integrations
     * @param array $seo_schema_tags
     * @param array $seo_data
     * @param array $custom_metadata
     * @param array $meta_tags
     * @param array $rich_content_images
     * @param array $rich_content_videos
     * @param array $rich_content_embeds
     * @param string $campaign_asset_guid
     * @return array|object
     */
    public function contentCreateOrUpdate(
        string $externalId,
        string $canonicalUrl,
        string $title,
        string $subtitle,
        string $featured_image_url,
        string $template_name,
        string $sponsorship,
        string $is_published,
        DateTime $publishedDate,
        DateTime $modifiedDate,
        string $content,
        array $authors = [],
        array $categories = [],
        array $tags = [],
        array $third_party_integrations = [],
        array $seo_schema_tags = [],
        array $seo_data = [],
        array $custom_metadata = [],
        array $meta_tags = [],
        array $rich_content_images = [],
        array $rich_content_videos = [],
        array $rich_content_embeds = [],
        string $campaign_asset_guid = null
    ) {
        // Validate the structure of the referenced metadata
        $authors = $this->metaArrayToObjects( $authors, 'authors' );
        $categories = $this->metaArrayToObjects( $categories, 'categories' );
        $tags = $this->metaArrayToObjects( $tags, 'tags' );
        $third_party_integrations = $this->metaArrayToObjects( $third_party_integrations, 'third_party_integrations' );
        $seo_schema_tags = $this->metaArrayToObjects( $seo_schema_tags, 'seo_schema_tags' );
        $seo_data = $this->metaArrayToObjects( $seo_data, 'seo_data' );
        $custom_metadata = $this->metaArrayToObjects( $custom_metadata, 'custom_metadata' );
        $meta_tags = $this->metaArrayToObjects( $meta_tags, 'meta_tags' );
        $rich_content_images = $this->metaArrayToObjects( $rich_content_images, 'rich_content_images' );
        $rich_content_videos = $this->metaArrayToObjects( $rich_content_videos, 'rich_content_videos' );
        $rich_content_embeds = $this->metaArrayToObjects( $rich_content_embeds, 'rich_content_embeds' );

        $mutation = ( new Mutation( 'contentCreateOrUpdate' ) );
        $mutation->setVariables( [ new Variable( 'input', 'CreateOrUpdateContentInput', true ) ] );
        $mutation->setArguments( [ 'input' => '$input' ] );
        $mutation->setSelectionSet( [ 'ok', 'gamId' ] );

        $variables = [
            'input' => [
                'authors' => $authors,
                'canonicalUrl' => $canonicalUrl,
                'categories' => $categories,
                'content' => $content,
                'externalId' => $externalId,
                'isPublished' => (bool) $is_published,
                'modifiedDate' => $modifiedDate->format( DateTimeInterface::ATOM ),
                'publishedDate' => $publishedDate->format( DateTimeInterface::ATOM ),
                'siteGuid' => $this->siteGuid,
                'tags' => $tags,
                'title' => $title,
                'thirdPartyIntegrations' => $third_party_integrations,
                'seoSchemaTags' => $seo_schema_tags,
                'seoData' => $seo_data,
                'customMetadata' => $custom_metadata,
                'metaTags' => $meta_tags,
                'richContentIndex' => array_merge( $rich_content_images, $rich_content_videos, $rich_content_embeds ),
                'campaignAssetGuid' => $campaign_asset_guid,
            ],
        ];
        if ( $subtitle ) {
            $variables['input']['subtitle'] = $subtitle;
        }
        if ( $featured_image_url ) {
            $variables['input']['featuredImageUrl'] = $featured_image_url;
        }
        if ( $template_name ) {
            $variables['input']['templateName'] = $template_name;
        }
        if ( $sponsorship ) {
            $variables['input']['sponsorship'] = $sponsorship;
        }

        $result = $this->runQuery( $mutation, $variables );
        return $result['data']['contentCreateOrUpdate'];
    }

    public function queryContentIdMap( $first, $skip ) {
        $gql = ( new Query( 'contentIdMap' ) );
        $gql->setArguments(
            [
                'siteGuid' => $this->siteGuid,
                'first' => $first,
                'skip' => $skip,
            ]
        );
        $gql->setSelectionSet(
            [
                ( new Query( 'edges' ) )->setSelectionSet(
                    [
                        ( new Query( 'node' ) )->setSelectionSet(
                            [
                                'externalId',
                                'gamId',
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
        );
        $result = $this->runQuery( $gql );
        return $result['data']['contentIdMap'];
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
                                        ( new Query( 'outbrain' ) )->setSelectionSet(
                                            [
                                                'enabled',
                                                'selectors',
                                                'relative',
                                            ]
                                        ),
                                        ( new Query( 'indexServer' ) )->setSelectionSet(
                                            [
                                                'enabled',
                                                'tag',
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
                                        'relative',
                                        'relativeSettings',
                                        'selectors',
                                        'prefillContainerCssClass',
                                        'limit',
                                        'isOutOfPage',
                                        'sizes',
                                        'pinHeightTo',
                                        'css',
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
                                        'loadBySdk',
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
            throw new RuntimeException( 'Organic API Failed', -1, $e );
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

    /**
     * Helper to check if the given array contains 'externalId' and 'name' keys.
     *
     * Used for checking on authors, categories and tags
     *
     * @param $array
     * @param $dataType
     * @throws InvalidArgumentException if a required value is missing
     */
    private function metaArrayToObjects( $array, $dataType ) {
        $objects = [];

        foreach ( $array as $value ) {
            if ( ! isset( $value['externalId'] ) || ! isset( $value['name'] ) ) {
                throw new InvalidArgumentException(
                    'Missing externalId or name attribute in ' . $dataType
                );
            }
            $objects[] = $value;
        }

        return $objects;
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
