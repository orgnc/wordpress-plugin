<?php

namespace Empire;

use DateTime;
use Empire\SDK\EmpireSdk;
use Exception;
use WP_Query;
use function App\get_article_author;
use function \get_user_by;
use function Sentry\captureException;

/**
 * Client Plugin for TrackADM.com ads, analytics and affiliate management platform
 */
class Empire {

    private $isEnabled = false;
    private $geoDetectionSource = null;
    private $defaultRegion = 'US';
    private $enabledVendors = array();
    private $enabledRegions = array();

    /**
     * @var AdsTxt
     */
    private $adsTxt;

    /**
     * @var API
     */
    private $api;

    /**
     * @var string Which CMP are we using, '', 'built-in' or 'one-trust'
     */
    private $cmp;

    /**
     * @var bool If we should be showing the Playspace player
     */
    private $connatixEnabled;

    /**
     * @var string Player ID to use for Playspace ads
     */
    private $connatixPlayspaceId;

    /**
     * @var bool Flag to preload ad slots with common heights for better CLS scores
     */
    private $enablePrefilledAdSlots = false;

    /**
     * @var int % of traffic to send to Empire SDK instead of TrackADM Pixel
     */
    private $empirePixelTestPercent = 0;

    /**
     * @var string|null Name of the split test to use (must be tied to GAM value in 'tests' key)
     */
    private $empirePixelTestValue = null;

    /**
     * @var string Empire environment
     */
    private $environment = 'PRODUCTION';

    /**
     * @var string UUID of the One Trust property that we are pulling if One Trust is set as the CMP
     */
    private $oneTrustId = '';

    /**
     * External ID of the pixel selected to inject on this site
     *
     * @var string|null
     */
    private $pixelId = null;

    /**
     * URL of the published version of the pixel
     *
     * @var string|null
     */
    private $pixelPublishedUrl;

    /**
     * URL of the testing version of the pixel
     *
     * @var string|null
     */
    private $pixelTestingUrl;

    /**
     * New Empire SDK
     *
     * @var EmpireSdk
     */
    public $sdk;

    /**
     * @var string|null API key for Empire
     */
    private $sdkKey;

    /**
     * @var string Site ID from Empire
     */
    private $siteId;

    /**
     * Create the Empire plugin ecosystem
     *
     * @param $environment string PRODUCTION or DEVELOPMENT
     */
    public function __construct( $environment ) {
        $this->environment = $environment;
        $apiKey = get_option( 'empire::api_key' );
        $this->sdkKey = get_option( 'empire::sdk_key' );
        $this->siteId = get_option( 'empire::site_id' );
        $this->api = new Api( $this->environment, $apiKey );
        $this->sdk = new EmpireSdk( $this->siteId, $this->sdkKey );

        $this->adsTxt = new AdsTxt( $this );

        $this->isEnabled = get_option( 'empire::enabled' );
        $this->cmp = get_option( 'empire::cmp' );
        $this->oneTrustId = get_option( 'empire::one_trust_id' );
        $this->enablePrefilledAdSlots = get_option( 'empire::prefill_ad_slots' );
        $this->empirePixelTestPercent = get_option( 'empire::percent_test' );
        $this->empirePixelTestValue = get_option( 'empire::test_value' );
        $this->geoDetectionSource = get_option( 'empire::geo_detection' );
        $this->defaultRegion = get_option( 'empire::default_region' );
        $this->enabledRegions = get_option( 'empire::enabled_regions', array() );
        $this->enabledVendors = get_option( 'empire::enabled_vendors', array() );

        $this->connatixEnabled = get_option( 'empire::connatix_enabled' );
        $this->connatixPlayspaceId = get_option( 'empire::connatix_playspace_id' );

        $this->pixelId = get_option( 'empire::pixel_id' );
        $this->pixelPublishedUrl = get_option( 'empire::pixel_published_url' );
        $this->pixelTestingUrl = get_option( 'empire::pixel_testing_url' );

        /* Load up our sub-page configs */
        new AdminSettings( $this );
        new CCPAPage( $this );
        new PostEditor( $this );
        new AdsTxt( $this );
        new PageInjection( $this );
        new ContentSyncCommand( $this );
        new ContentIdMapSyncCommand( $this );

        add_action( 'init', array( $this, '_registerGATagTaxonomy' ), 0 );
        add_action( 'the_content', array( $this, 'substituteTags' ) );

        add_filter( 'empire_tag_link', array( $this, 'tagLink' ), 10, 3 );
    }

    public function getEnvironment() {
         return $this->environment;
    }

    /**
     * If we are configured to do server level Geo detection, then returns
     * the Region Code of the matching, detected region.
     *
     * If the region cannot be detected or is not an enabled region, then
     * returns the default region code.
     *
     * @return string|null Region Code for the detected region for the user
     */
    public function getGeo(): ?string {
        $code = $this->defaultRegion;

        if ( $this->isEnabled && $this->geoDetectionSource ) {
            if ( $this->geoDetectionSource == 'cloudflare' ) {
                $code = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ?
                    $_SERVER['HTTP_CF_IPCOUNTRY'] :
                    null;
            }
        }

        // Remap the GB code to our "UK" code. All others are fine as default
        if ( $code == 'GB' ) {
            $code = 'UK';
        }

        // Only return enabled regions - if the detected region isn't enabled
        // then we return the default region
        if ( $code ) {
            foreach ( $this->enabledRegions as $region ) {
                if ( $region['code'] == $code ) {
                    return $code;
                }
            }
        }

        return $this->defaultRegion;
    }

    /**
     * Gets the tag to use for this vendor and this region or null if none is
     * set.
     *
     * @param string $vendorCode
     * @param string $regionCode
     * @return string|null
     */
    public function getDefaultTag(
        string $vendorCode,
        string $regionCode
    ): ?string {
        foreach ( $this->enabledVendors as $vendor ) {
            if ( $vendor['code'] == $vendorCode ) {
                if ( isset( $vendor['default_tags'] ) && isset( $vendor['default_tags'][ $regionCode ] ) ) {
                    return $vendor['default_tags'][ $regionCode ];
                }
            }
        }

        return null;
    }

    /**
     * Figure out which tag should be placed on all of the URLs in this post
     * for the given vendor and region.
     *
     * If no post-specific tags exist, then looks up the chain to find the right
     * site-wide tag.
     *
     * @param string $vendorCode
     * @param string $regionCode
     * @param int $postID
     * @return string|null
     */
    public function getPostTag(
        string $vendorCode,
        string $regionCode,
        int $postID
    ): ?string {
        $key = 'empire::tag_' . $vendorCode . '_' . $regionCode;
        $tag = get_post_meta( $postID, $key, true );

        if ( ! $tag ) {
            $tag = $this->getDefaultTag( $vendorCode, $regionCode );
        }

        return $tag;
    }

    public function filterAmazonProductLink( $usLink, $postID, $productName ) {
        $geo = $this->getGeo();

        if ( $geo == 'US' ) {
            return $this->tagAmazonLink( $usLink, $postID );
        } else {
            return $this->getAmazonSearchLink( $productName, $postID );
        }
    }

    /**
     * Custom function for tagging Amazon product URLs
     *
     * @param $link
     * @param $postID
     * @return string
     */
    public function tagAmazonLink( $link, $postID ) {
        $geo = $this->getGeo();
        $tag = $this->getPostTag( 'amzn', $geo, $postID );

        $urlParts = parse_url( $link );

        /* @todo update this to be region-specific domain */
        if ( ! isset( $urlParts['host'] ) ) {
            $urlParts['host'] = 'www.amazon.com';
        }

        $amazonDomains = array(
            'US' => 'www.amazon.com',
            'UK' => 'www.amazon.co.uk',
            'AU' => 'www.amazon.com.au',
            'CA' => 'www.amazon.ca',
            'BR' => 'www.amazon.com.br',
            'CN' => 'www.amazon.cn',
            'FR' => 'www.amazon.fr',
            'DE' => 'www.amazon.de',
            'IN' => 'www.amazon.in',
            'IT' => 'www.amazon.it',
        );
        if ( isset( $amazonDomains[ $geo ] ) ) {
            $urlParts['host'] = $amazonDomains[ $geo ];
        }

        $queryParams = array();
        parse_str( $urlParts['query'], $queryParams );
        $queryParams['tag'] = $tag;

        $urlParts['path'] = preg_replace( '/&.+/', '', $urlParts['path'] );
        $urlParts['path'] = preg_replace( '/tag=.+/', '', $urlParts['path'] );

        $query = http_build_query( $queryParams );

        $url = 'https://' . $urlParts['host'] . $urlParts['path'] . '?' . $query;

        return $url;
    }

    /**
     * Generates a properly tagged Amazon affiliate URL for a search page
     *
     * @param $term
     * @param $postID
     * @return string
     */
    public function getAmazonSearchLink( $term, $postID ) {
        $url = 'https://www.amazon.com/s/?url=search-alias&field-keywords=' .
            urlencode( $term );
        return $this->tagAmazonLink( $url, $postID );
    }

    /**
     * Figures out which vendor to apply the links for and then calls that
     * vendor's tagging mechanism to apply the proper tag and/or domain changes.
     *
     * If the product link cannot be directly internationalized, then generates
     * a search link (e.g. with Amazon).
     *
     * @param $usLink
     * @param $postID
     * @param $productName
     * @return string
     */
    public function tagLink( $usLink, $postID, $productName ) {
        if ( ! $this->isEnabled ) {
            return $usLink;
        }

        $vendorCode = self::detectVendor( $usLink );

        if ( $vendorCode == 'amzn' ) {
            return $this->filterAmazonProductLink( $usLink, $postID, $productName );
        } else {
            return $usLink;
        }
    }

    /**
     * Checks the link and figures out the vendor code for the given link or
     * null if it's not known.
     *
     * Vendor Codes:
     *  - amzn
     *  - endurance
     *  - autopom
     *
     * @param $link
     * @return string|null
     */
    public static function detectVendor( $link ) : ?string {
        $parts = parse_url( $link );
        if ( ! $parts ) {
            return null;
        }

        if ( preg_match( '/amazon/', $parts['host'] ) ) {
            return 'amzn';
        }

        if ( $parts['host'] == 'endurancewarranty.com' ) {
            return 'endurance';
        }

        if ( $parts['extended-vehicle-warranty.com'] ) {
            return 'autopom';
        }

        return null;
    }

    /**
     * Returns true if Empire localization and tagging is enabled
     *
     * @return bool
     */
    public function isEnabled() : bool {
        return $this->isEnabled;
    }

    /**
     * Returns true if we are supposed to hide the footer links and URL injection for
     * consent management (e.g. if it is being handled by a 3rd party like One Trust)
     *
     * @return bool
     */
    public function getCmp() : bool {
        return $this->cmp;
    }

    /**
     * Checks if we should be using the Built In CMP
     *
     * @return bool
     */
    public function useCmpBuiltIn() : bool {
        return $this->isEnabled() && $this->cmp == 'built-in';
    }

    /**
     * Checks if we are using One Trust
     */
    public function useCmpOneTrust() : bool {
        return $this->isEnabled() && $this->cmp === 'one-trust' && $this->getOneTrustId();
    }

    /**
     * Check if the Connatix Playspace player is configured and enabled
     *
     * @return bool
     */
    public function useConnatix() : bool {
        return $this->isEnabled() && $this->connatixEnabled && $this->connatixPlayspaceId;
    }

    /**
     * Get the Player ID for the Connatix Playspace player
     *
     * @return string
     */
    public function getConnatixPlayspaceId() : string {
        return $this->connatixPlayspaceId;
    }

    /**
     * Get the Site ID for the One Trust code injection
     *
     * @return string
     */
    public function getOneTrustId() {
        return $this->oneTrustId;
    }

    /**
     * Returns true if we are supposed to fill in certain ad slots with presized blanks
     *
     * @return bool
     */
    public function prefillAdSlots() : bool {
        return $this->enablePrefilledAdSlots;
    }

    /**
     * Sychronizes a single Post to Empire
     *
     * @param $post
     */
    public function syncPost( $post ) {
        $canonical = get_permalink( $post->ID );

        // Cleanup canonicals - hack for initial migrations from dev
        $canonical = str_replace( 'lcl.taskandpurpose', 'taskandpurpose', $canonical );
        $canonical = str_replace( 'dev.taskandpurpose', 'taskandpurpose', $canonical );
        $canonical = str_replace( 'stg.taskandpurpose', 'taskandpurpose', $canonical );
        $canonical = str_replace( 'lcl.', 'www.', $canonical );
        $canonical = str_replace( 'dev.', 'www.', $canonical );
        $canonical = str_replace( 'stg.', 'www.', $canonical );
        $canonical = str_replace( 'http://', 'https://', $canonical );

        $title = $post->post_title;
        $external_id = $post->ID;
        $content = $post->post_content;
        $published_date = $post->post_date;
        $modified_date = $post->post_modified;

        $authors = array();

        // Assume the default Wordpress author structure. Need to override this for our
        // taxonomy-driven author data
        if ( $post->post_author ) {
            $user = get_user_by( 'id', $post->post_author );
            if ( $user ) {
                $authors[] = array(
                    'externalId' => (string) $post->post_author,
                    'name' => $user->display_name,
                );
            }
        }

        // If we are using our advanced author taxonomy support, then override with that
        $author_support_data = get_field( 'opt_author_support', 'option' );
        $author_support_enabled = true;
        if (
            ! isset( $author_support_data['enabled'] ) ||
            empty( $author_support_data['enabled'] )
        ) {
            $author_support_enabled = false;
        }
        if (
            $author_support_enabled &&
            function_exists( 'get_article_author' )
        ) {
            $authors = array();
            $author_data = get_article_author( $post->ID );
            foreach ( $author_data as $author ) {
                $authors[] = array(
                    'externalId' => (string) $author['id'],
                    'name' => $author['name'],
                );
            }
        }

        $categories = array();
        foreach ( wp_get_post_categories( $post->ID ) as $category_id ) {
            $category = get_category( $category_id );
            $categories[] = array(
                'externalId' => (string) $category->term_id,
                'name' => $category->name,
            );
        }

        $tags = array();
        foreach ( wp_get_post_tags( $post->ID ) as $tag_id ) {
            $tag = get_tag( $tag_id );
            $tags[] = array(
                'externalId' => (string) $tag->term_id,
                'name' => $tag->name,
            );
        }

        $this->debug( 'Empire Sync: external_id=' . $external_id . ', url=' . $canonical );

        try {
            $result = $this->sdk->contentCreateOrUpdate(
                $external_id,
                $canonical,
                $title,
                new DateTime( $published_date ),
                new DateTime( $modified_date ),
                $content,
                $authors,
                $categories,
                $tags
            );
        } catch ( \Exception $e ) {
            // We should manually let Sentry know about this, since theoretically the API
            // shouldn't error out here.
            captureException( $e );

            // Either way, don't disrupt the CMS operations about it
            return null;
        }

        if ( $result->gamId ) {
            update_post_meta( $post->ID, 'empire_gam_id', $result->gamId );
        } else {
            delete_post_meta( $post->ID, 'empire_gam_id' );
        }

        // Mark the post as synchronized to exclude from the next batch
        update_post_meta( $post->ID, 'empire_sync', 'synced' );
    }

    /**
     * Helper function to actually execute sync calls to Empire for posts that come back from
     * WP_Query
     *
     * @param $query
     * @return int # of posts sync-ed
     * @throws Exception
     */
    private function _syncQueryResults( $query ) {
        $updated = 0;

        // We only look at the first page of results
        $posts = $query->get_posts();

        foreach ( $posts as $post ) {
            // post should have at least title
            if ( ! $post->post_title ) {
                continue;
            }

            $this->syncPost( $post );
            $updated++;
        }

        return $updated;
    }

    /**
     * Build a query that looks at all posts that we want to keep in sync with Empire
     *
     * @param int $per_page
     * @return WP_Query
     */
    public function buildQueryAllSyncablePosts( $per_page = 1000 ) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
        );
        return new WP_Query( $args );
    }

    /**
     * Build a query that can find the posts that have never been synced to Empire
     *
     * @param int $per_page # of posts per page
     * @return WP_Query
     */
    public function buildQueryNeverSyncedPosts( $per_page = 1000 ) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'meta_query' => array(
                array(
                    'key' => 'empire_sync',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );
        return new WP_Query( $args );
    }

    /**
     * Build a query that can find the posts that have been synced before but have changed
     *
     * @param int $per_page # of posts per page
     * @return WP_Query
     */
    public function buildQueryNewlyUnsyncedPosts( $per_page = 1000 ) {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'meta_query' => array(
                array(
                    'key' => 'empire_sync',
                    'value' => 'synced',
                    'compare' => '!=',
                ),
            ),
        );
        return new WP_Query( $args );
    }

    /**
     * Finds a batch of posts that have not been synchronized with Empire yet and publish their info
     *
     * Works in batches of 1000 to minimize load issues
     *
     * @return int Number of posts synchronized
     * @throws Exception if posts have invalid published or modified dates
     */
    public function syncContent() : int {
        $max_to_sync = 1000;

        // First go through ones that have never been sync-ed
        $post_query = $this->buildQueryNeverSyncedPosts( $max_to_sync );
        $updated = $this->_syncQueryResults( $post_query );

        // Cap our calls
        if ( $updated >= $max_to_sync ) {
            return $updated;
        }

        // If we are under the limit, find posts that have been recently updated
        $post_query = $this->buildQueryNewlyUnsyncedPosts( $max_to_sync - $updated );
        $this->_syncQueryResults( $post_query );

        return $updated;
    }

    /**
     * Pulls current Content Id Map and updates GAM Ids for articles
     */
    public function syncContentIdMap() {
        global $wpdb;

        $mapping = array();
        $stats = array(
            'deleted' => 0,
            'untouched' => 0,
            'skipped' => 0,
            'cleaned' => 0,
            'reassigned' => 0,
            'created' => 0,
            'total' => 0,
        );

        $first = 100; // batch size
        $skip = 0;
        $count = 0;

        // pull mapping of gamIds to externalIds
        while ( true ) {
            $id_map = $this->sdk->queryContentIdMap( $first, $skip );
            $total_objects = $id_map->pageInfo->totalObjects;
            foreach ( $id_map->edges as $edge ) {
                $mapping[ $edge->node->gamId ] = $edge->node->externalId;
                $count++;
            }

            if ( $count >= $total_objects ) {
                break;
            }

            $skip += $first;
        }

        $should_skip = function ( $post_id, $gam_id, $prefix = 'Skipping' ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                $this->debug( $prefix . ' gamId(' . $gam_id . ') for ' . $post_id . ' - no such post' );
                return true;
            }
            if ( $post->post_type != 'post' ) {
                $this->debug( $prefix . ' gamId(' . $gam_id . ') for ' . $post_id . " - not 'post' type" );
                return true;
            }
            return false;
        };

        $pop_by_key = function ( &$mapping, $key ) {
            $value = $mapping[ $key ] ?? null;
            unset( $mapping[ $key ] );
            return $value;
        };

        // delete and reassign current gamIds
        $metas = $wpdb->get_results(
            "SELECT pm.meta_id AS id, pm.post_id, pm.meta_value AS gam_id
             FROM {$wpdb->postmeta} pm
             WHERE pm.meta_key = 'empire_gam_id'"
        );
        $this->debug( 'Found mapped gamIds in DB: ' . count( $metas ) );
        foreach ( $metas as $meta ) {
            $external_id = $pop_by_key( $mapping, $meta->gam_id );

            if ( ! $external_id ) {
                // gamId was reassigned to different site or deleted
                $this->debug( 'Deleting gamId(' . $meta->gam_id . ') for ' . $meta->post_id );
                delete_meta( $meta->id );
                $stats['deleted']++;
                continue;
            }

            if ( $should_skip( $external_id, $meta->gam_id, 'Cleaning up' ) ) {
                // cleanup bogus data, only real `posts` should have gamId
                delete_meta( $meta->id );
                $stats['cleaned']++;
                continue;
            }

            if ( $external_id == $meta->post_id ) {
                // gamId still assigned to the same post
                $stats['untouched']++;
                continue;
            }

            // gamId was re-assgined to different post
            $this->debug( 'Re-assigning gamId(' . $meta->gam_id . ') from ' . $meta->post_id . ' to ' . $external_id );
            delete_meta( $meta->id );
            update_post_meta( $external_id, 'empire_gam_id', $meta->gam_id );
            $stats['reassigned']++;
        }

        // set new gamIds
        foreach ( $mapping as $gam_id => $external_id ) {
            if ( $should_skip( $external_id, $gam_id ) ) {
                $stats['skipped']++;
                continue;
            }

            $this->debug( 'Setting gamId(' . $gam_id . ') for ' . $external_id );
            update_post_meta( $external_id, 'empire_gam_id', $gam_id );
            $stats['created']++;
        }

        $stats['total'] = array_sum( $stats );

        return $stats;
    }

    public function substituteTags( string $content ) : string {
        global $post;

        // Check to see if the plugin is even turned on
        $enabled = get_option( 'empire::enabled' );
        if ( ! $enabled ) {
            return $content;
        }

        if ( ! $post || ! is_object( $post ) ) {
            return $content;
        }

        // check if we have a tracking tag for this post
        $tag = get_post_meta( $post->ID, 'empire_tag', true );
        if ( ! $tag ) {
            return $content;
        }

        return $content;
    }

    public function getApi() : Api {
        return $this->api;
    }

    public function getAdsTxtManager() : AdsTxt {
        return $this->adsTxt;
    }

    /**
     * @return string|null
     */
    public function getPixelId(): ?string {
        return $this->pixelId;
    }

    /**
     * @return string|null
     */
    public function getPixelPublishedUrl(): ?string {
        return $this->pixelPublishedUrl;
    }

    /**
     * @return string|null
     */
    public function getPixelTestingUrl(): ?string {
        return $this->pixelTestingUrl;
    }

    /**
     * @param int|null $pixelId
     */
    public function setPixelId( ?int $pixelId ): void {
        $this->pixelId = $pixelId;
    }

    /**
     * @param string|null $pixelPublishedUrl
     */
    public function setPixelPublishedUrl( ?string $pixelPublishedUrl ): void {
        $this->pixelPublishedUrl = $pixelPublishedUrl;
    }

    /**
     * @param string|null $pixelTestingUrl
     */
    public function setPixelTestingUrl( ?string $pixelTestingUrl ): void {
        $this->pixelTestingUrl = $pixelTestingUrl;
    }

    /**
     * @return string|null
     */
    public function getSiteId(): ?string {
        return $this->siteId;
    }

    /**
     * @return string|null SDK Key (if set)
     */
    public function getSdkKey(): ?string {
        return $this->sdkKey;
    }

    /**
     * @return string|null
     */
    public function getEmpirePixelTestValue(): ?string {
        return $this->empirePixelTestValue;
    }

    /**
     * @return int
     */
    public function getEmpirePixelTestPercent(): int {
        return $this->empirePixelTestPercent;
    }

    public function log( $message ) {
        if ( class_exists( '\WP_CLI' ) ) {
            \WP_CLI::log( $message );
        }
    }

    public function debug( $message ) {
        if ( class_exists( '\WP_CLI' ) ) {
            \WP_CLI::debug( $message, 'empire' );
        }
    }

    public function _registerGATagTaxonomy() {
        $labels = array(
            'name' => _x( 'GA Tags', 'Google Analytics Tag', 'text_domain' ),
            'singular_name' => _x( 'GA Tags', 'Taxonomy Singular Name', 'text_domain' ),
            'menu_name' => __( 'GA Tags', 'text_domain' ),
            'all_items' => __( 'All GA Tags', 'text_domain' ),
            'new_item_name' => __( 'New GA Tag', 'text_domain' ),
            'add_new_item' => __( 'Add New GA Tag', 'text_domain' ),
            'edit_item' => __( 'Edit GA Tag', 'text_domain' ),
            'update_item' => __( 'Update GA Tag', 'text_domain' ),
            'view_item' => __( 'View GA Tag', 'text_domain' ),
            'add_or_remove_items' => __( 'Add or remove GA Tags', 'text_domain' ),
            'popular_items' => __( 'Popular GA Tags', 'text_domain' ),
            'search_items' => __( 'Search GA Tags', 'text_domain' ),
        );
        $args = array(
            'description' => 'Google Analytics Related Tag',
            'labels' => $labels,
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => false,
            'show_in_rest' => true,
        );
        register_taxonomy( 'ga_tag', array( 'post' ), $args );
    }
}
