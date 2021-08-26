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
     * @var array Configuration for AMP
     */
    private $ampConfig = null;

    /**
     * @var array Configuration for Ads
     */
    private $adsConfig = null;

    /**
     * List of Post Types that we are synchronizing with Empire Platform
     *
     * @var string[]
     */
    private $postTypes;

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
        $this->siteDomain = get_option( 'empire::site_domain' );
        $this->api = new Api( $this->environment, $apiKey );
        $this->sdk = new EmpireSdk( $this->siteId, $this->sdkKey );

        $this->adsTxt = new AdsTxt( $this );

        $this->isEnabled = get_option( 'empire::enabled' );
        $this->ampAdsEnabled = get_option( 'empire::amp_ads_enabled' );
        $this->cmp = get_option( 'empire::cmp' );
        $this->oneTrustId = get_option( 'empire::one_trust_id' );
        $this->enablePrefilledAdSlots = get_option( 'empire::prefill_ad_slots' );
        $this->empirePixelTestPercent = get_option( 'empire::percent_test' );
        $this->empirePixelTestValue = get_option( 'empire::test_value' );

        $this->connatixEnabled = get_option( 'empire::connatix_enabled' );
        $this->connatixPlayspaceId = get_option( 'empire::connatix_playspace_id' );

        $this->pixelId = get_option( 'empire::pixel_id' );
        $this->pixelPublishedUrl = get_option( 'empire::pixel_published_url' );
        $this->pixelTestingUrl = get_option( 'empire::pixel_testing_url' );

        $this->postTypes = get_option('empire::post_types', ['post', 'page']);

        /* Load up our sub-page configs */
        new AdminSettings( $this );
        new CCPAPage( $this );
        new AdsTxt( $this );
        new PageInjection( $this );
        new ContentSyncCommand( $this );
        new ContentIdMapSyncCommand( $this );
        new AdConfigSyncCommand( $this );
        new AdsTxtSyncCommand( $this );
    }

    public function getEnvironment() {
        return $this->environment;
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
     * Post types that we synchronize with Empire Platform
     *
     * @return string[]
     */
    public function getPostTypes()
    {
        return $this->postTypes;
    }

    /**
     * Update the list of post types we synchronize and inject ads on
     */
    public function setPostTypes($types)
    {
        $this->postTypes = $types;
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
     * Check if the AMP Ads are configured and enabled
     *
     * @return bool
     */
    public function useAmpAds() : bool {
        return $this->isEnabled() && $this->ampAdsEnabled;
    }

    public function getAmpConfig() : array {
        if ( !empty($this->ampConfig) ) {
            return $this->ampConfig;
        }

        $ampConfig = get_option( 'empire::ad_amp_config' );
        if ( empty($ampConfig)) {
            $this->ampConfig = [
                'forPlacement' => [],
            ];
            return $this->ampConfig;
        }

        $forPlacement = array_reduce($ampConfig['placements'], function($byKey, $amp) {
            $byKey[$amp['key']] = $amp;
            return $byKey;
        }, []);

        $this->ampConfig = [
            'forPlacement' => $forPlacement,
        ];
        return $this->ampConfig;
    }

    public function getAdsConfig() : array {
        if ( !empty($this->adsConfig) ) {
            return $this->adsConfig;
        }

        $adsConfig = get_option( 'empire::ad_settings' );
        if ( empty($adsConfig)) {
            $this->adsConfig = [
                'adRules' => [],
                'forPlacement' => [],
            ];
            return $this->adsConfig;
        }

        $forPlacement = array_reduce($adsConfig['placements'], function($byKey, $place) {
            $byKey[$place['key']] = $place;
            return $byKey;
        }, []);

        $this->adsConfig = [
            'adRules' => $adsConfig['adRules'],
            'forPlacement' => $forPlacement,
        ];
        return $this->adsConfig;
    }

    public function getCurrentUrl() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    public function getCanonicalUrlFor( $postId ) {
        $canonical = get_permalink( $postId );

        // Cleanup canonicals - hack for initial migrations from dev
        $canonical = str_replace( 'lcl.taskandpurpose', 'taskandpurpose', $canonical );
        $canonical = str_replace( 'dev.taskandpurpose', 'taskandpurpose', $canonical );
        $canonical = str_replace( 'stg.taskandpurpose', 'taskandpurpose', $canonical );
        $canonical = str_replace( 'lcl.', 'www.', $canonical );
        $canonical = str_replace( 'dev.', 'www.', $canonical );
        $canonical = str_replace( 'stg.', 'www.', $canonical );
        $canonical = str_replace( 'http://', 'https://', $canonical );

        return $canonical;
    }

    public function getKeywordsFor( $postId ) {
        $keywords = get_the_tags( $postId );

        if ( $keywords && is_array( $keywords ) ) {
            return array_map(
                function( $tag ) {
                    return $tag->slug;
                },
                $keywords
            );
        }
        return [];
    }

    public function getCategoryForCurrentPage() {
        if ( is_single() ) {
            $id = esc_html( get_the_ID() );
            $category = $this->getArticlePrimaryCategory( $id );
            if ( $category && isset( $category['primary_category'] ) ) {
                return $category['primary_category']['obj'] ?? null;
            } else {
                return null;
            }
        } else if ( is_category() ) {
            return get_queried_object();
        } else {
            return null;
        }
    }

    public function getTargeting() {
        $post = get_post();

        $url = $this->getCurrentUrl();
        $keywords = $this->getKeywordsFor( $post->ID );
        $category = $this->getCategoryForCurrentPage();

        $id = '';
        $gamId = '';
        if ( is_single() ) {
            $id = esc_html( get_the_ID() );
            $gamId = get_post_meta( $id, 'empire_gam_id', true );
        } else if ( is_category() ) {
            $id = 'channel-' . $category->slug;
        } else if ( is_page() ) {
            $id = 'page-' . $post->post_name;
        }
        $gamPageId = $gamId ? $gamId : $id;
        $gamExternalId = $id;

        return [
            'siteDomain' => $this->siteDomain,
            'url' => $url,
            'keywords' => $keywords,
            'category' => $category,
            'gamPageId' => $gamPageId,
            'gamExternalId' => $gamExternalId,
        ];
    }

    /**
     * Get the article's primary category/all categories
     *
     * Uses Yoast SEO primary if it set, otherwise uses the first category
     *
     * @param $article_id
     * @param string $term
     * @param false $return_all_categories
     * @return array
     */
    public function getArticlePrimaryCategory(
        $article_id,
        $term = 'category',
        $return_all_categories = false
    ) {
        $result = array();

        if ( class_exists( '\WPSEO_Primary_Term' ) ) {
            // Show Primary category by Yoast if it is enabled & set
            $wpseo_primary_term = new \WPSEO_Primary_Term( $term, $article_id );
            $primary_term = get_term( $wpseo_primary_term->get_primary_term() );

            if ( ! is_wp_error( $primary_term ) ) {
                $result['primary_category'] = array(
                    'obj' => $primary_term,
                    'link' => get_term_link( $primary_term ),
                );
            }
        }

        if ( empty( $result['primary_category'] ) || $return_all_categories ) {
            $categories_list = get_the_terms( $article_id, $term );
            if ( empty( $return['primary_category'] ) && ! empty( $categories_list ) ) {
                $last_category = end( $categories_list );
                $result['primary_category'] = array(
                    'obj' => $last_category,
                    'link' => get_term_link( $last_category ),
                );  //get the first category
            }
            if ( $return_all_categories ) {
                $result['all_categories'] = array();

                array_pop( $categories_list );
                if ( ! empty( $categories_list ) ) {
                    foreach ( $categories_list as &$category ) {
                        $result['all_categories'][] = array(
                            'obj' => $category,
                            'link' => get_term_link( $category ),
                        );
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Synchronizes a single Post to Empire
     *
     * @param $post
     */
    public function syncPost( $post ) {
        $canonical = $this->getCanonicalUrlFor( $post->ID );

        # In order to support non-standard post metadata, we have a filter for each attribute
        $canonical = apply_filter('empire_post_url', $canonical);
        $title = apply_filter('empire_post_title', $post->post_title);
        $external_id = apply_filter('empire_post_id', $post->ID);
        $content = apply_filter('empire_post_content', $post->post_content);
        $published_date = apply_filter('empire_post_publish_date', $post->post_date);
        $modified_date = apply_filter('empire_post_modified_date', $post->post_modified);

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
        // (requires Advanced Custom Fields plugin)
        $author_support_enabled = false;
        if ( function_exists('get_field') ) {
            $author_support_data = get_field( 'opt_author_support', 'option' );
            $author_support_enabled = true;
            if (
                ! isset( $author_support_data['enabled'] ) ||
                empty( $author_support_data['enabled'] )
            ) {
                $author_support_enabled = false;
            }
        }
        if (
            $author_support_enabled &&
            function_exists( 'App\get_article_author' )
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

        if ( $result['gamId'] ) {
            update_post_meta( $post->ID, 'empire_gam_id', $result['gamId'] );
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
            'post_type' => $this->getPostTypes(),
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
            'post_type' => $this->getPostTypes(),
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
            'post_type' => $this->getPostTypes(),
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
            $total_objects = $id_map['pageInfo']['totalObjects'];
            foreach ( $id_map['edges'] as $edge ) {
                $mapping[ $edge['node']['gamId'] ] = $edge['node']['externalId'];
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
            if ( ! in_array( $post->post_type, $this->getPostTypes()) ) {
                $this->debug( $prefix . ' gamId(' . $gam_id . ') for ' . $post_id . " - not synchable type" );
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

    public function syncAdConfig() {
        $config = $this->sdk->queryAdConfig();

        $this->debug( 'Got site domain: ' . $config['domain'] );
        update_option( 'empire::site_domain', $config['domain'], false );

        $this->debug( 'Got Ad Settings: ' . json_encode( $config['settings'] ) );
        update_option( 'empire::ad_settings', $config['settings'], false );

        $this->debug( 'Got Amp Config: ' . json_encode( $config['ampConfig'] ) );
        update_option( 'empire::ad_amp_config', $config['ampConfig'], false );

        return array(
            'updated' => true,
        );
    }

    public function syncAdsTxt() {
        $ads_txt_content = $this->sdk->queryAdsTxt();
        $old_ads_txt = $this->getAdsTxtManager()->get();

        // Make sure there was actually a change
        if ( $old_ads_txt == $ads_txt_content ) {
            return;
        }

        // If there was a change then trigger the update
        $this->getAdsTxtManager()->update( $ads_txt_content );

        // and clear CDN (only Fastly supported so far)
        if (
            ! is_plugin_active( 'fastly/purgely.php' ) ||
            ! class_exists( 'Purgely_Purge' )
        ) {
            return;
        }

        // This fails silently for now since we don't have much control over the user's config
        $purgely = new \Purgely_Purge();
        $purgely->purge( \Purgely_Purge::URL, get_home_url( null, '/ads.txt' ) );

        // Add in a hook that can be used to purge more complex caches
        do_action( 'empire_ads_txt_changed' );
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
}
