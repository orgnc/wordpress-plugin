<?php

namespace Empire\SDK;

use DateTime;
use DateTimeInterface;
use Exception;
use GraphQL\Client;
use GraphQL\Exception\QueryError;
use GraphQL\Mutation;
use GraphQL\Variable;
use GraphQL\Query;
use GraphQL\RawObject;
use GraphQL\Tests\RawObjectTest;
use InvalidArgumentException;
use RuntimeException;

/**
 * Communicate with the Empire APIs (GraphQL and REST)
 *
 * @package Empire\Internals
 */
class EmpireSdk {


    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var Client GraphQL Client
     */
    private $client;

    /**
     * @var string GUID for the site we are working on
     */
    private $siteGuid;

    /**
     * Set up everything we need to work with the Empire API in the context of a single site.
     *
     * @param $siteGuid
     * @param string|null $token
     * @param string $url
     */
    public function __construct(
        string $siteGuid,
        ?string $token = null,
        string $url = 'https://api.empireio.com/graphql'
    ) {
         $params = array();
        if ( $token ) {
            $params['x-api-key'] = $token;
        }
        $this->apiUrl = $url;
        $this->client = new Client(
            $url,
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
     * Builds the SDK URL to embed the JS SDK into web pages
     *
     * @return string
     */
    public function getSdkUrl() {
          return 'https://empireio.com/sdk/unit-sdk.js?' . $this->siteGuid;
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
            array(
                'externalId' => $externalId,
                'name' => $name,
                'siteGuid' => $this->siteGuid,
            )
        );
        $mutation->setSelectionSet( array( 'ok' ) );
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
     * @param DateTime $publishedDate
     * @param DateTime $modifiedDate
     * @param string $content
     * @param array $authors
     * @param array $categories
     * @param array $tags
     * @return array|object
     */
    public function contentCreateOrUpdate(
        string $externalId,
        string $canonicalUrl,
        string $title,
        DateTime $publishedDate,
        DateTime $modifiedDate,
        string $content,
        array $authors = array(),
        array $categories = array(),
        array $tags = array()
    ) {
         // Validate the structure of the referenced metadata
        $authors = $this->metaArrayToObjects( $authors, 'authors' );
        $categories = $this->metaArrayToObjects( $categories, 'categories' );
        $tags = $this->metaArrayToObjects( $tags, 'tags' );

        $mutation = ( new Mutation( 'contentCreateOrUpdate' ) );
        $mutation->setVariables( array( new Variable( 'input', 'CreateOrUpdateContentInput', true ) ) );
        $mutation->setArguments( array( 'input' => '$input' ) );
        $mutation->setSelectionSet( array( 'ok', 'gamId' ) );

        $variables = array(
            'input' => array(
                'authors' => $authors,
                'canonicalUrl' => $canonicalUrl,
                'categories' => $categories,
                'content' => $content,
                'externalId' => $externalId,
                'modifiedDate' => $modifiedDate->format( DateTimeInterface::ATOM ),
                'publishedDate' => $publishedDate->format( DateTimeInterface::ATOM ),
                'siteGuid' => $this->siteGuid,
                'tags' => $tags,
                'title' => $title,
            ),
        );
        $result = $this->runQuery( $mutation, $variables );
        return $result['data']['contentCreateOrUpdate'];
    }

    public function queryContentIdMap( $first, $skip ) {
        $gql = ( new Query( 'contentIdMap' ) );
        $gql->setArguments(
            array(
                'siteGuid' => $this->siteGuid,
                'first' => $first,
                'skip' => $skip,
            )
        );
        $gql->setSelectionSet(
            array(
                ( new Query( 'edges' ) )->setSelectionSet(
                    array(
                        ( new Query( 'node' ) )->setSelectionSet(
                            array(
                                'externalId',
                                'gamId',
                            )
                        ),
                    )
                ),
                ( new Query( 'pageInfo' ) )->setSelectionSet(
                    array(
                        'totalObjects',
                    )
                ),
            )
        );
        $result = $this->runQuery( $gql );
        return $result['data']['contentIdMap'];
    }

    public function queryAdConfig() {
         $gql = ( new Query( 'appAds' ) );
        $gql->setArguments(
            array(
                'siteGuids' => array( $this->siteGuid ),
            )
        );
        $gql->setSelectionSet(
            array(
                ( new Query( 'sites' ) )->setSelectionSet(
                    array(
                        'domain',
                        ( new Query( 'settings' ) )->setSelectionSet(
                            array(
                                ( new Query( 'adSettings' ) )->setSelectionSet(
                                    array(
                                        'enableRefresh',
                                        'tabletBreakpointMin',
                                        'desktopBreakpointMin',
                                        ( new Query( 'amazon' ) )->setSelectionSet(
                                            array(
                                                'enabled',
                                                'pubId',
                                            )
                                        ),
                                        ( new Query( 'indexServer' ) )->setSelectionSet(
                                            array(
                                                'enabled',
                                                'tag',
                                            )
                                        ),
                                        ( new Query( 'nonRefresh' ) )->setSelectionSet(
                                            array(
                                                'advertiserIds',
                                                'lineitemIds',
                                            )
                                        ),
                                    )
                                ),
                                ( new Query( 'adRules' ) )->setSelectionSet(
                                    array(
                                        'guid',
                                        'component',
                                        'comparator',
                                        'value',
                                        'enabled',
                                    )
                                ),
                                ( new Query( 'placements' ) )->setSelectionSet(
                                    array(
                                        'guid',
                                        'key',
                                        'name',
                                        'description',
                                        'adUnitId',
                                        'relative',
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
                                        'refreshEnabled',
                                        'refreshStrategy',
                                        'disablePrebid',
                                        'disableAmazon',
                                    )
                                ),
                                ( new Query( 'prebid' ) )->setSelectionSet(
                                    array(
                                        'enabled',
                                        'timeout',
                                        ( new Query( 'bidders' ) )->setSelectionSet(
                                            array(
                                                'key',
                                                'name',
                                                'enabled',
                                                'placementSettings',
                                                'bidAssignment',
                                                'bidCpmAdjustment',
                                            )
                                        ),
                                    )
                                ),
                            )
                        ),
                        ( new Query( 'ampConfig' ) )->setSelectionSet(
                            array(
                                ( new Query( 'placements' ) )->setSelectionSet(
                                    array(
                                        'key',
                                        'html',
                                    )
                                ),
                                'requiredScripts',
                            )
                        ),
                        ( new Query( 'prefillConfig' ) )->setSelectionSet(
                            array(
                                ( new Query( 'placements' ) )->setSelectionSet(
                                    array(
                                        'key',
                                        'html',
                                        'css',
                                    )
                                ),
                            )
                        ),
                    )
                ),
            )
        );
        $result = $this->runQuery( $gql );
        return $result['data']['appAds']['sites'][0];
    }

    public function queryAdsTxt(): string {
        $gql = ( new Query( 'adsTxt' ) );
        $gql->setArguments(
            array(
                'siteGuid' => $this->siteGuid,
            )
        );
        $gql->setSelectionSet(
            array(
                'text',
            )
        );
        $result = $this->runQuery( $gql );
        return $result['data']['adsTxt']['text'];
    }

    /**
     * Helper for standardized error handling when running a GraphQL query or mutation
     *
     * @param $query
     * @param array $variables
     * @return array|object
     * @throws RuntimeException if API returns a failure code
     */
    private function runQuery( $query, array $variables = array() ) {
        try {
            $result = $this->client->runQuery( $query, true, $variables );
            $responseCode = $result->getResponseObject()->getStatusCode();
            if ( $responseCode > 201 ) {
                throw new RuntimeException( 'Empire API Failed with Error Code ' . $responseCode );
            }

            return $result->getResults();
        } catch ( QueryError $e ) {
            throw new RuntimeException( 'Empire API Failed', -1, $e );
        }
    }

    /**
     * Update the GraphQL client to use a new token
     *
     * @param string|null $token
     */
    public function updateToken( ?string $token ) {
        $params = array();
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
        $objects = array();

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
}
