<?php

namespace Organic;

use DateTime;
use DateTimeImmutable;
use Organic\SDK\OrganicSdk;
use Exception;
use Sentry\State\Hub;
use WP_Post;
use WP_Query;

use function \get_user_by;

const CAMPAIGN_ASSET_META_KEY = 'empire_campaign_asset_guid';
const GAM_ID_META_KEY = 'empire_gam_id';
const SYNC_META_KEY = 'empire_sync';


/**
 * Client Plugin for the Organic Platform
 */
class Organic {
    public $version = \Organic\ORGANIC_PLUGIN_VERSION;

    const DEFAULT_PLATFORM_URL = 'https://app.organic.ly';
    const DEFAULT_REST_API_URL = 'https://api.organiccdn.io';

    private $isEnabled = false;

    private $logToSentry = true;

    /**
     * @var ?Hub
     */
    private $sentryHub = null;

    /**
     * @var AdsTxt
     */
    private $adsTxt;

    /**
     * @var string Which CMP are we using, '', 'built-in' or 'one-trust'
     */
    private $cmp;

    /**
     * @var bool True if we are forcing Content Meta synchronization into foreground
     */
    private $contentForeground = false;

    /**
     * @var bool True if we want to modify the RSS feeds to include featured images
     */
    private $feedImages = false;

    /**
     * @var bool True if we need to load via JavaScript
     */
    private $splitTestEnabled = false;

    /**
     * @var int % of traffic to send to Organic SDK instead of default implementation
     */
    private $splitTestPercent = 0;

    /**
     * @var string|null Name of the split test to use (must be tied to GAM value in 'tests' key)
     */
    private $splitTestKey = null;

    /**
     * @var string Organic environment
     */
    private $environment = 'PRODUCTION';

    /**
     * @var string UUID of the One Trust property that we are pulling if One Trust is set as the CMP
     */
    private $oneTrustId = '';

    /**
     * New Organic SDK
     *
     * @var OrganicSdk
     */
    public $sdk;

    /**
     * @var string|null API key for Organic
     */
    private $sdkKey;

    /**
     * @var string Site ID from Organic
     */
    private $siteId;

    /**
     * @var array Configuration for AMP
     */
    private $ampConfig = null;

    /**
     * @var array Configuration for Prefill
     */
    private $prefillConfig = null;

    /**
     * @var AdsConfig Configuration for Ads
     */
    private $adsConfig = null;

    /**
     * List of Post Types that we are synchronizing with Organic Platform
     *
     * @var string[]
     */
    private $postTypes;

    /**
     * @var bool If Organic App is enabled in the Platform
     */
    private $campaignsEnabled = false;

    /**
     * @var bool If Organic App is enabled in the Platform
     */
    private $affiliateEnabled = false;

    private static $instance = null;

    /**
     * @var false|void The public-facing domain for the Affiliate App
     */
    private $affiliateDomain = null;

    /**
     * Main purpose is to allow access to the plugin instance from the `wp shell`:
     *  >>> $organic = Organic\Organic::getInstance()
     *  => Organic\Organic {#1829
     *       +sdk: Organic\SDK\OrganicSdk {#1830},
     *       +"siteDomain": "domino.com",
     *       +"ampEnabled": "1",
     *       +"prefillEnabled": "1",
     *     }
     */
    public static function getInstance(): Organic {
        return static::$instance;
    }

    /**
     * Create the Organic plugin ecosystem
     *
     * @param $environment string PRODUCTION or DEVELOPMENT
     */
    public function __construct( string $environment, ?Hub $sentryHub ) {
        $this->environment = $environment;
        // If enabled (the default), we'll send errors to Organic Sentry.
        $this->sentryHub = $sentryHub;
        static::$instance = $this;
    }

    /**
     * Context-Aware Translator to wrap __()
     *
     * If running in "TEST" environment, then this short circuits to return the $text.
     *
     * @param $text
     * @param $domain
     * @return mixed
     */
    public function t( $text ) {
        if ( $this->getEnvironment() == 'TEST' ) {
            return $text;
        } else {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            return __( $text, 'organic' );
        }
    }

    public function getEnvironment() {
        return $this->environment;
    }

    /**
     * Safe wrapper for Wordpress $this->getOption call
     *
     * Supports transitional backward compatibility with the name change to Organic.
     *
     * @param $name
     * @return mixed
     */
    public function getOption( $name, $default = false ) {
        if ( function_exists( 'get_option' ) ) {
            $result = get_option( $name );

            // Fallback to old version if it exists instead
            if ( false === $result ) {
                $result = get_option( str_replace( 'organic::', 'empire::', $name ), $default );
            }
            return $result;
        } else {
            return $default;
        }
    }

    /**
     * Safe wrapper for Wordpress update_option call.
     *
     * Supports transitional backward compatibility with the name change to Organic.
     *
     * @param $name
     * @param $value
     * @param bool $autoload
     * @return void
     */
    public function updateOption( $name, $value, $autoload = false ) {
        if ( function_exists( 'update_option' ) ) {
            // Update old value as well for backward compatibility
            update_option( str_replace( 'organic::', 'empire::', $name ), $value, $autoload );

            $updated = update_option( $name, $value, $autoload );
            if ( $updated ) {
                update_option( 'organic::settings_last_updated', new Datetime() );
            }
            return $updated;
        } else {
            return null;
        }
    }

    /**
     * Sets up config options for our operational context
     * @param $apiUrl string|null
     * @param string|null $cdnUrl
     */
    public function init( $apiUrl = null, $cdnUrl = null ) {
        $this->sdkKey = $this->getOption( 'organic::sdk_key' );
        $this->siteId = $this->getOption( 'organic::site_id' );
        $this->siteDomain = $this->getOption( 'organic::site_domain' );
        $this->sdk = new OrganicSdk( $this->siteId, $this->sdkKey, $apiUrl, $cdnUrl );

        $this->adsTxt = new AdsTxt( $this );

        $this->isEnabled = $this->getOption( 'organic::enabled' );
        $this->logToSentry = $this->getOption( 'organic::log_to_sentry' );
        // Reinitialize Sentry with a client-specific key if applicable.
        if ( $this->isEnabled && $this->logToSentry ) {
            $this->configureSentryForSite();
        }

        // Uses old `amp_ads_enabled` but controls AMP overall
        $this->ampEnabled = $this->getOption( 'organic::amp_ads_enabled' );
        // Uses old `ad_slots_prefill` but controls Prefill overall
        $this->prefillEnabled = $this->getOption( 'organic::ad_slots_prefill_enabled' );
        $this->cmp = $this->getOption( 'organic::cmp' );
        $this->oneTrustId = $this->getOption( 'organic::one_trust_id' );
        $this->splitTestEnabled = $this->getOption( 'organic::test_mode' );
        $this->splitTestPercent = intval( $this->getOption( 'organic::percent_test' ) );
        $this->splitTestKey = $this->getOption( 'organic::test_value' );

        $this->feedImages = $this->getOption( 'organic::feed_images' );

        $this->postTypes = $this->getOption( 'organic::post_types', [ 'post', 'page' ] );

        $this->campaignsEnabled = $this->getOption( 'organic::campaigns_enabled' );
        $this->contentForeground = $this->getOption( 'organic::content_foreground' );

        $this->affiliateEnabled = $this->getOption( 'organic::affiliate_enabled' );
        $this->affiliateDomain = $this->getOption( 'organic::affiliate_domain' );

        if ( is_admin() ) {
            new AdminSettings( $this );
        }

        new CCPAPage( $this );
        new PageInjection( $this );
        new ContentSyncCommand( $this );
        new ContentIdMapSyncCommand( $this );
        new AdConfigSyncCommand( $this );
        new AdsTxtSyncCommand( $this );
        new AffiliateConfigSyncCommand(
            $this,
            'organic-sync-affiliate-config',
            'organic_cron_sync_affiliate_config'
        );
        new PluginConfigSyncCommand(
            $this,
            'organic-sync-plugin-config',
            'organic_cron_sync_plugin_config'
        );

        // Set up our GraphQL hooks to expose settings
        $graphql = new GraphQL( $this );
        $graphql->init();

        // Set up affiliate app
        if ( $this->useAffiliate() ) {
            new Affiliate( $this );
        }

        add_action( 'save_post', [ $this, 'handleSavePostHook' ], 10, 3 );
    }

    /**
     * When applicable, configures the Organic Sentry client with a site-specific DSN.
     * @return void
     */
    public function configureSentryForSite() {
        $sentryDSN = $this->getOption( 'organic::sentry_dsn' );
        if ( ! empty( $sentryDSN ) ) {
            $this->sentryHub = init_organic_sentry( $sentryDSN, $this->getEnvironment() );
        }
    }

    /**
     * Returns true if Organic integration is Enabled
     *
     * @return bool
     */
    public function isEnabled() : bool {
        return $this->isEnabled;
    }

    /**
     * Returns true if `SDK Key` and `Site ID` are configured.
     * Does not check validity of the values!
     *
     * @return bool
     */
    public function isConfigured() : bool {
        return $this->getSdkKey() && $this->getSiteId();
    }

    /**
     * Returns true if Organic integration is enabled and properly configured
     *
     * @return bool
     */
    public function isEnabledAndConfigured() : bool {
        return $this->isEnabled() && $this->isConfigured();
    }

    public function adsTxtRedirectionEnabled() : bool {
        return $this->getOption( 'organic::ads_txt_redirect_enabled' );
    }

    /**
     * Returns the timestamp of the last update to the Organic settings.
     * Defaults to the current timestamp.
     *
     * @return DateTime
     */
    public function settingsLastUpdated() : DateTime {
        $lastUpdate = $this->getOption( 'organic::settings_last_updated' );
        if ( $lastUpdate ) {
            return $lastUpdate;
        }
        return new DateTime();
    }

    /**
     * Returns true if we need to load the SDK via JavaScript for a split test
     *
     * @return bool
     */
    public function useSplitTest() : bool {
        return (
            $this->isEnabledAndConfigured() &&
            $this->splitTestEnabled &&
            $this->getSplitTestKey() &&
            ( $this->getSplitTestPercent() !== null ) &&
            $this->getSplitTestPercent() <= 100 &&
            $this->getSplitTestPercent() >= 0
        );
    }

    /**
     * Returns the content management platform. If it exists,
     * we should hide the footer links and URL injection for
     * consent management (since it is being handled by a 3rd party like One Trust)
     *
     * @return string
     */
    public function getCmp() : string {
        return $this->cmp;
    }

    /**
     * Post types that we synchronize with Organic Platform
     *
     * @return string[]
     */
    public function getPostTypes() {
        return $this->postTypes;
    }

    /**
     * Update the list of post types we synchronize and inject ads on
     */
    public function setPostTypes( $types ) {
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
     * Get the Site ID for the One Trust code injection
     *
     * @return string
     */
    public function getOneTrustId() {
        return $this->oneTrustId;
    }

    // TODO: we should be able to enabled/disable Ads too
    public function useAds() : bool {
        return $this->isEnabledAndConfigured();
    }

    /**
     * Returns if Campaigns app is enabled
     *
     * @return bool
     */
    public function useCampaigns() : bool {
        return $this->isEnabledAndConfigured() && $this->campaignsEnabled;
    }

    /**
     * Returns if Affiliate app is enabled
     *
     * @return bool
    */
    public function useAffiliate() : bool {
        return $this->isEnabledAndConfigured() && $this->affiliateEnabled;
    }

    /**
     * Check if the AMP Ads are configured and enabled
     *
     * @return bool
     */
    public function useAmp() : bool {
        return $this->isEnabledAndConfigured() && $this->ampEnabled;
    }

    public function usePrefill() : bool {
        return $this->isEnabledAndConfigured() && $this->prefillEnabled;
    }

    public function getAffiliateDomain() {
        return $this->affiliateDomain;
    }

    /**
     * @param $content string|null
     * @return bool|int
     */
    public function eligibleForSDK( $content = null ) : bool {
        global $wp_query;

        if ( is_admin() || wp_doing_ajax() || is_feed() ) {
            return false;
        }

        $post = get_post();

        if ( ! (
            $wp_query->is_home ||
            $wp_query->is_category ||
            $wp_query->is_tag ||
            $wp_query->is_tax ||
            $wp_query->is_archive ||
            $wp_query->is_search ||
            ( $post && in_array( $post->post_type, $this->getPostTypes() ) )
        ) ) {
            return false;
        }

        if ( is_string( $content ) ) {
            // Additional check that this is HTML if content blob was provided
            return preg_match( '/<\/html>/i', $content );
        }

        return true;
    }

    /**
     * @param $content string|null
     * @return bool|int
     */
    public function useAdsOnPage( $content = null ) : bool {
        // Allow to disable Ads by hook
        $eligibleForAds = apply_filters( 'organic_eligible_for_ads', $this->eligibleForSDK( $content ) );
        return $this->useAds() && $eligibleForAds;
    }

    /**
     * @param $content string|null
     * @return bool|int
     */
    public function useAffiliateOnPage( $content = null ) : bool {
        // Allow to disable Affiliate by hook
        $eligibleForAffiliate = apply_filters( 'organic_eligible_for_affiliate', $this->eligibleForSDK( $content ) );
        return $this->useAffiliate() && $eligibleForAffiliate;
    }

    public function getAmpConfig() : AmpConfig {
        if ( ! empty( $this->ampConfig ) ) {
            return $this->ampConfig;
        }

        $rawAmpConfig = $this->getOption( 'organic::ad_amp_config', [] );
        $this->ampConfig = new AmpConfig( $rawAmpConfig );

        return $this->ampConfig;
    }

    public function getPrefillConfig() : PrefillConfig {
        if ( ! empty( $this->prefillConfig ) ) {
            return $this->prefillConfig;
        }

        $rawPrefillConfig = $this->getOption( 'organic::ad_prefill_config', [] );
        $this->prefillConfig = new PrefillConfig( $rawPrefillConfig );

        return $this->prefillConfig;
    }

    public function getAdsConfig() : AdsConfig {
        if ( ! empty( $this->adsConfig ) ) {
            return $this->adsConfig;
        }

        $rawAdsConfig = $this->getOption( 'organic::ad_settings', [] );
        $rawAdsRefreshRates = $this->getOption( 'organic::ads_refresh_rates', [] );
        $this->adsConfig = new AdsConfig( $rawAdsConfig, $rawAdsRefreshRates );

        return $this->adsConfig;
    }

    public function getCurrentUrl() {
        $scheme = is_ssl() ? 'https' : 'http';
        return esc_url( $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
    }

    public function getTagsFor( $postId ) {
        $keywords = get_the_tags( $postId );

        if ( ! is_array( $keywords ) ) {
            return [];
        }
        return $this->getSlugs( ...$keywords );
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

    public function getSlugs( ...$terms ) {
        return array_map(
            function( $term ) {
                return $term->slug;
            },
            array_filter(
                $terms,
                function( $term ) {
                    return is_a( $term, 'WP_Term' );
                }
            )
        );
    }

    /**
     * Returns term's nesting level
     *
     * @param mixed $term
     * @param int $maxLevel
     * @return int
     */
    public function getTermLevel( $term, $maxLevel = 5 ) {
        $level = 1;
        $parent = $term;
        for ( ;; ) {
            if ( $parent->parent == 0 || $level >= $maxLevel ) {
                break;
            }
            $parent = get_term_by( 'term_id', $parent->parent, $term->taxonomy );
            $level++;
        }
        return $level;
    }

    public function getTargeting() {
        $post = get_post();

        $keywords = [];
        if ( $post ) {
            $keywords = $this->getTagsFor( $post->ID );
        }
        $url = $this->getCurrentUrl();
        $category = $this->getCategoryForCurrentPage();
        $sections = null;

        $id = '';
        $gamId = '';
        if ( is_single() ) {
            $id = esc_html( get_the_ID() );
            $gamId = get_post_meta( $id, GAM_ID_META_KEY, true );

            $categories = get_the_terms( $post->ID, 'category' ) ?: [];
            usort(
                $categories,
                function ( $a, $b ) {
                    return $a->term_id - $b->term_id;
                }
            );

            $c1terms = array_filter(
                $categories,
                function( $term ) {
                    return $this->getTermLevel( $term ) == 1;
                }
            );

            $c2terms = array_filter(
                $categories,
                function( $term ) {
                    return $this->getTermLevel( $term ) > 1;
                }
            );

            // $category can be set by WPSEO_Primary_Term
            $sections = array_unique( $this->getSlugs( $category, ...$c1terms ) );
            $keywords = array_merge(
                $this->getSlugs( ...$c2terms ),
                $keywords
            );
        } else if ( is_category() ) {
            $id = 'channel-' . $category->slug;
            $sections = [ $category->slug ];
        } else if ( is_page() ) {
            $id = 'page-' . $post->post_name;
        }
        $gamPageId = $gamId ? $gamId : $id;
        $gamExternalId = $id;

        $targeting = [
            'siteDomain' => $this->siteDomain,
            'url' => $url,
            'sections' => $sections,
            'keywords' => $keywords,
            'category' => $category,
            'gamPageId' => $gamPageId,
            'gamExternalId' => $gamExternalId,
        ];
        return \apply_filters( 'organic_targeting', $targeting );
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
        $result = [];

        if ( class_exists( '\WPSEO_Primary_Term' ) ) {
            // Show Primary category by Yoast if it is enabled & set
            $wpseo_primary_term = new \WPSEO_Primary_Term( $term, $article_id );
            $primary_term = get_term( $wpseo_primary_term->get_primary_term() );

            if ( ! is_wp_error( $primary_term ) ) {
                $result['primary_category'] = [
                    'obj' => $primary_term,
                    'link' => get_term_link( $primary_term ),
                ];
            }
        }

        if ( empty( $result['primary_category'] ) || $return_all_categories ) {
            $categories_list = get_the_terms( $article_id, $term );
            if ( empty( $return['primary_category'] ) && ! empty( $categories_list ) ) {
                $last_category = end( $categories_list );
                $result['primary_category'] = [
                    'obj' => $last_category,
                    'link' => get_term_link( $last_category ),
                ];  //get the first category
            }
            if ( $return_all_categories ) {
                $result['all_categories'] = [];

                array_pop( $categories_list );
                if ( ! empty( $categories_list ) ) {
                    foreach ( $categories_list as &$category ) {
                        $result['all_categories'][] = [
                            'obj' => $category,
                            'link' => get_term_link( $category ),
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Checks if post is eligible for sync
     *
     * @param $post
     */
    public function isPostEligibleForSync( $post ) {
        // sync only real 'posts' not revisions or attachments
        if ( ! in_array( $post->post_type, $this->getPostTypes() ) ) {
            return false;
        }

        // sync only published posts
        if ( $post->post_status != 'publish' ) {
            return false;
        }

        // post should have at least title
        if ( ! $post->post_title ) {
            return false;
        }

        return true;
    }

    /**
     *  Synchronizes the full tree of categories
     *  @return void|null
     */
    public function syncCategories() {
        $categories = get_categories( [ 'hide_empty' => false ] );
        $cat_id_map = [];
        $trees = [];
        foreach ( $categories as $cat ) {
            $cat_id_map[ $cat->term_id ] = [
                'externalId' => (string) $cat->term_id,
                'name' => $cat->name,
                'children' => [],
            ];
        }
        foreach ( $categories as &$cat ) {
            $cat_data = &$cat_id_map[ $cat->term_id ];
            if ( $cat->parent == 0 ) {
                array_push( $trees, $cat_data );
            } else {
                $parent = &$cat_id_map[ $cat->parent ];
                $children = &$parent['children'];
                if ( is_array( $children ) ) {
                    array_push( $children, $cat_data );
                }
            }
        }
        foreach ( $trees as &$tree ) {
            $tree['siteGuid'] = $this->siteId;
            try {
                $this->sdk->categoryTreeUpdate( $tree );
            } catch ( \Exception $e ) {
                $this::captureException( $e );
            }
        }
    }

    /**
     * Synchronizes a single Post to Organic
     *
     * @param WP_Post $post
     * @return void|null
     */
    public function syncPost( WP_Post $post ) {
        if ( ! $this->isPostEligibleForSync( $post ) ) {
            $this->debug(
                'Organic Sync: SKIPPED',
                [
                    'post_id' => $post->ID,
                    'post_type' => $post->post_title,
                    'post_status' => $post->post_status,
                    'post_title' => $post->post_title,
                ]
            );
            return null;
        }

        $canonical = get_permalink( $post );
        $edit_url = \Organic\get_edit_post_link( $post );

        # In order to support non-standard post metadata, we have a filter for each attribute
        $external_id = \apply_filters( 'organic_post_id', $post->ID );
        $canonical = \apply_filters( 'organic_post_url', $canonical, $post->ID );
        $featured_image_url = \apply_filters( 'organic_post_featured_image_url', get_the_post_thumbnail_url( $post ), $post->ID );
        $title = \htmlspecialchars_decode( $post->post_title );
        $title = \apply_filters( 'organic_post_title', $title, $post->ID );

        $content = \get_post_field( 'post_content', $post );
        $content = \apply_filters( 'the_content', $content );
        $content = \apply_filters( 'organic_post_content', $content, $post->ID );

        $published_date = \apply_filters( 'organic_post_publish_date', $post->post_date, $post->ID );
        $modified_date = \apply_filters( 'organic_post_modified_date', $post->post_modified, $post->ID );
        $campaign_asset_guid = null;
        if ( $this->useCampaigns() ) {
            $campaign_asset_guid = get_post_meta( $post->ID, CAMPAIGN_ASSET_META_KEY, true );
            if ( $campaign_asset_guid == '' ) {
                $campaign_asset_guid = null;
            }
        }

        $meta_description = get_the_excerpt( $post );
        if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
            $meta_description = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) ?: $meta_description;
        }
        $meta_description = \apply_filters( 'organic_post_meta_description', $meta_description, $post->ID );

        $authors = [];
        // Assume the default Wordpress author structure
        $author_id = get_post_field( 'post_author', $post->ID );
        if ( $author_id ) {
            if ( get_user_by( 'id', $author_id ) ) {
                $authors[] = [
                    'externalId' => (string) $author_id,
                    'name' => get_the_author_meta( 'display_name', $author_id ),
                    'email' => get_the_author_meta( 'email', $author_id ),
                    'imageUrl' => get_avatar_url( $author_id ),
                ];
            }
        }
        unset( $author_id ); // Cleanup after ourselves
        // Allow sites to augment the assumed author data
        $authors = \apply_filters( 'organic_post_authors', $authors, $post->ID );

        $categories = [];
        foreach ( wp_get_post_categories( $post->ID ) as $category_id ) {
            $category = get_category( $category_id );
            $categories[] = [
                'externalId' => (string) $category->term_id,
                'name' => $category->name,
            ];
        }

        $tags = [];
        foreach ( wp_get_post_tags( $post->ID ) as $tag_id ) {
            $tag = get_tag( $tag_id );
            $tags[] = [
                'externalId' => (string) $tag->term_id,
                'name' => $tag->name,
            ];
        }

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
                $tags,
                $campaign_asset_guid,
                $edit_url,
                $featured_image_url,
                $meta_description
            );
        } catch ( \Exception $e ) {
            // We should manually let Sentry know about this, since theoretically the API
            // shouldn't error out here.
            $this::captureException( $e );
            $this->warning(
                'Organic Sync: ERROR',
                [
                    'external_id' => $external_id,
                    'url' => $canonical,
                ]
            );

            // Either way, don't disrupt the CMS operations about it
            return null;
        }
        $this->debug(
            'Organic Sync: SUCCESS',
            [
                'external_id' => $external_id,
                'url' => $canonical,
            ]
        );

        if ( $result['gamId'] ) {
            update_post_meta( $post->ID, GAM_ID_META_KEY, $result['gamId'] );
        } else {
            delete_post_meta( $post->ID, GAM_ID_META_KEY );
        }

        // Mark the post as synchronized to exclude from the next batch
        update_post_meta( $post->ID, SYNC_META_KEY, 'synced' );
    }

    /**
     * Helper function to actually execute sync calls to Organic Platform for posts
     *
     * @param WP_Post[] $posts
     * @return int # of posts sync-ed
     * @throws Exception
     */
    private function _syncPosts( array $posts ): int {
        $updated = 0;
        foreach ( $posts as $post ) {
            $this->syncPost( $post );
            $updated++;
        }

        return $updated;
    }

    /**
     * Build a query that looks at all posts that we want to keep in sync with Organic
     *
     * @param int $batch
     * @param int $offset
     * @param array|null $meta
     * @return WP_Query
     */
    public function buildQuerySyncablePosts( $batch = 1000, $offset = 0, $meta = null ) {
        $args = [
            'post_type' => $this->getPostTypes(),
            'post_status' => 'publish',
            'posts_per_page' => $batch,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
        ];

        if ( ! empty( $meta ) ) {
            $args['meta_query'] = $meta;
        }

        return new WP_Query( $args );
    }

    /**
     * Build a query that can find the posts that have never been synced to Organic
     *
     * @param int $batch # of posts per page
     * @param int $offset
     * @return WP_Query
     */
    public function buildQueryNeverSyncedPosts( $batch = 1000, $offset = 0 ) {
        return $this->buildQuerySyncablePosts(
            $batch,
            $offset,
            [
                [
                    'key' => SYNC_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ]
        );
    }

    /**
     * Build a query that can find the posts that have been synced before but have changed
     *
     * @param int $batch # of posts per page
     * @param int $offset
     * @return WP_Query
     */
    public function buildQueryNewlyUnsyncedPosts( $batch = 1000, $offset = 0 ) {
        return $this->buildQuerySyncablePosts(
            $batch,
            $offset,
            [
                [
                    'key' => SYNC_META_KEY,
                    'value' => 'synced',
                    'compare' => '!=',
                ],
            ]
        );
    }

    /**
     * Finds a batch of posts that have not been synchronized with Organic yet and publish their info
     *
     * Works in batches of 1000 to minimize load issues
     *
     * @param int $max_to_sync Number of posts to attempt to sync
     * @return int Number of posts synchronized
     * @throws Exception if posts have invalid published or modified dates
     */
    public function syncContent( $max_to_sync = 1000 ) : int {
        // First go through ones that have never been sync-ed
        $query = $this->buildQueryNeverSyncedPosts( $max_to_sync );
        $updated = $this->_syncPosts( $query->posts );

        // Cap our calls
        if ( $updated >= $max_to_sync ) {
            return $updated;
        }

        // If we are under the limit, find posts that have been recently updated
        $query = $this->buildQueryNewlyUnsyncedPosts( $max_to_sync - $updated );
        $updated += $this->_syncPosts( $query->posts );

        return $updated;
    }

    /**
     * Re-syncs all eligible posts
     *
     * @param int $batch
     * @param int $sleep_between
     * @return int Number of posts synchronized
     * @throws Exception if posts have invalid published or modified dates
     */
    public function fullResyncContent( $batch = 50, $offset = 0, $sleep_between = 0 ) : int {
        $this->updateContentResyncStartedAt();

        $updated = 0;

        while ( true ) {
            $query = $this->buildQuerySyncablePosts( $batch, $offset );
            $updated += $this->_syncPosts( $query->posts );
            $this->debug(
                'Organic Sync: SYNCING',
                [
                    'updated' => $updated,
                    'offset' => $offset,
                    'found_posts' => $query->found_posts,
                    'max_num_pages' => $query->max_num_pages,
                    'post_count' => $query->post_count,
                    'request' => $query->request,
                ]
            );

            if ( $query->post_count < $batch ) {
                break;
            }

            if ( $sleep_between > 0 ) {
                sleep( $sleep_between );
            }

            $offset += $batch;
        }

        return $updated;
    }

    public function getContentResyncStartedAt(): DateTimeImmutable {
        return DateTimeImmutable::createFromFormat(
            DATE_ATOM,
            $this->getOption(
                'organic::content_resync_started_at',
                '2003-05-27T05:07:53+00:00'
            )
        );
    }

    public function updateContentResyncStartedAt(): bool {
        $this->updateOption(
            'organic::content_resync_started_at',
            current_datetime()->format( DATE_ATOM )
        );
        return true;
    }

    public function contentResyncTriggeredRecently(): bool {
        return 1 > $this->getContentResyncStartedAt()->diff( current_datetime(), true )->days;
    }

    public function triggerContentResync(): DateTimeImmutable {
        wp_cache_delete( 'organic::content_resync_started_at', 'options' );
        if ( false === $this->contentResyncTriggeredRecently() ) {
            global $wpdb;
            $wpdb->get_results(
                $wpdb->prepare(
                    "UPDATE $wpdb->postmeta SET meta_value = 'unsynced' WHERE meta_key = %s",
                    SYNC_META_KEY
                )
            );
            $this->updateContentResyncStartedAt();
        }
        wp_cache_delete( 'organic::content_resync_started_at', 'options' );
        return $this->getContentResyncStartedAt();
    }

    /**
     * Pulls current Content Id Map and updates GAM Ids for articles
     */
    public function syncContentIdMap() {
        global $wpdb;

        $mapping = [];
        $stats = [
            'deleted' => 0,
            'untouched' => 0,
            'skipped' => 0,
            'cleaned' => 0,
            'reassigned' => 0,
            'created' => 0,
            'total' => 0,
        ];

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
            if ( ! in_array( $post->post_type, $this->getPostTypes() ) ) {
                $this->debug( $prefix . ' gamId(' . $gam_id . ') for ' . $post_id . ' - not synchable type' );
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
            $wpdb->prepare(
                "SELECT pm.meta_id AS id, pm.post_id, pm.meta_value AS gam_id
                FROM {$wpdb->postmeta} pm
                WHERE pm.meta_key = %s",
                GAM_ID_META_KEY
            )
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
            update_post_meta( $external_id, GAM_ID_META_KEY, $meta->gam_id );
            $stats['reassigned']++;
        }

        // set new gamIds
        foreach ( $mapping as $gam_id => $external_id ) {
            if ( $should_skip( $external_id, $gam_id ) ) {
                $stats['skipped']++;
                continue;
            }

            $this->debug( 'Setting gamId(' . $gam_id . ') for ' . $external_id );
            update_post_meta( $external_id, GAM_ID_META_KEY, $gam_id );
            $stats['created']++;
        }

        $stats['total'] = array_sum( $stats );

        return $stats;
    }

    /**
     * Hook to see any newly created or updated posts and make sure we mark them as "dirty" for
     * future Organic sync
     *
     * @param $post_ID
     * @param $post
     * @param $update
     */
    public function handleSavePostHook( $post_ID, $post, $update ) {
        if ( ! $this->isEnabledAndConfigured() ) {
            return;
        }

        if ( ! $this->isPostEligibleForSync( $post ) ) {
            return;
        }

        update_post_meta( $post_ID, SYNC_META_KEY, 'unsynced' );

        if ( $this->useSyncPostOnSave() ) {
            $this->syncPost( $post );
        }
    }

    public function syncAdConfig() {
        $config = $this->sdk->queryAdConfig();

        $this->debug( 'Got site domain: ' . $config['domain'] );
        $this->updateOption( 'organic::site_domain', $config['domain'], false );

        $this->debug( 'Got Ad Settings: ', $config['settings'] );
        $this->updateOption( 'organic::ad_settings', $config['settings'], false );

        $ampConfig = $config['ampConfig'] ?? [];
        $this->debug( 'Got Amp Config: ', $ampConfig );
        $this->updateOption( 'organic::ad_amp_config', $ampConfig, false );

        $prefillConfig = $config['prefillConfig'] ?? [];
        $this->debug( 'Got Prefill Config: ', $prefillConfig );
        $this->updateOption( 'organic::ad_prefill_config', $prefillConfig, false );

        $this->syncAdsRefreshRates();

        return [
            'updated' => true,
        ];
    }

    public function syncAdsRefreshRates() {
        $rates = $this->sdk->queryAdsRefreshRates();

        $this->debug( 'Got ads refresh rates: ', $rates );
        $this->updateOption( 'organic::ads_refresh_rates', $rates, false );
    }

    public function syncAdsTxt() {
        $ads_txt_content = $this->sdk->queryAdsTxt();
        $old_ads_txt = $this->getAdsTxtManager()->get();

        // Make sure there was actually a change
        if ( $old_ads_txt == $ads_txt_content ) {
            return [
                'updated' => false,
                'cache_purged' => false,
            ];
        }

        // If there was a change then trigger the update
        $this->getAdsTxtManager()->update( $ads_txt_content );

        // and clear CDN (only Fastly supported so far)
        if (
            ! is_plugin_active( 'fastly/purgely.php' ) ||
            ! class_exists( 'Purgely_Purge' )
        ) {
            return [
                'updated' => true,
                'cache_purged' => false,
            ];
        }

        // This fails silently for now since we don't have much control over the user's config
        $purgely = new \Purgely_Purge();
        $purgely->purge( \Purgely_Purge::URL, get_home_url( null, '/ads.txt' ) );

        // Add in a hook that can be used to purge more complex caches
        do_action( 'organic_ads_txt_changed' );

        return [
            'updated' => true,
            'cache_purged' => true,
        ];
    }

    public function syncAffiliateConfig() {
        try {
            $config = $this->sdk->queryAffiliateConfig();
        } catch ( \Exception $e ) {
            self::captureException( $e );
            return [
                'updated' => false,
            ];
        }

        $domain = $config['publicDomain'];
        $this->debug( 'Got affiliate domain: ' . $domain );
        $this->updateOption( 'organic::affiliate_domain', $domain, false );
        return [
            'updated' => true,
        ];
    }

    public function syncPluginConfig() {
        try {
            $config = $this->sdk->mutateAndQueryWordPressConfig( $this );
            if ( empty( $config ) ) {
                throw new \UnexpectedValueException( 'Empty response from sdk->mutateAndQueryWordPressConfig', 204 );
            }

            $sentryDSN = $config['sentryDsn'];
            if ( ! empty( $sentryDSN ) ) {
                $this->updateOption( 'organic::sentry_dsn', $sentryDSN, false );
                $this->configureSentryForSite();
            }
            if ( true === $config['triggerContentResync'] ) {
                $this->triggerContentResync();
            }
        } catch ( \Exception $e ) {
            self::captureException( $e );
            return [
                'updated' => false,
            ];
        }

        return [
            'updated' => true,
        ];
    }

    public function getAdsTxtManager() : AdsTxt {
        return $this->adsTxt;
    }

    /**
     * Check if we are configured for foreground synchronization.
     * This does not block background / cron based synchronization as well,
     * but may make your saves slower for the editors.
     *
     * @return bool
     */
    public function useSyncPostOnSave() : bool {
        return $this->contentForeground;
    }

    /**
     * Indicates if the plugin is configured to inject Featured Images into the RSS feed (for Connatix)
     *
     * @return bool
     */
    public function useFeedImages() : bool {
        return $this->feedImages;
    }

    /**
     * @return string|null
     */
    public function getSiteId() {
        return $this->siteId;
    }

    /**
     * @return string|null SDK Key (if set)
     */
    public function getSdkKey() {
        return $this->sdkKey;
    }

    /**
     * @return string|null
     */
    public function getSplitTestKey() {
        return $this->splitTestKey;
    }

    /**
     * @return int
     */
    public function getSplitTestPercent(): int {
        return $this->splitTestPercent;
    }

    public static function captureException( \Exception $e ) {
        // If there is a current (non-Organic) hub, log the error using that hub.
        if ( \Sentry\SentrySdk::getCurrentHub()->getClient() ) {
            \Sentry\captureException( $e );
        }
        if ( self::$instance->getEnvironment() != 'PRODUCTION' ) {
            error_log( $e->getMessage() );
        }
        if ( ! self::$instance->sentryHub ) {
            return;
        }
        // Also log the error to Organic if an Organic hub is configured.
        self::$instance->sentryHub->captureException( $e );
    }

    public function loadCampaignsAssets() {
        if ( $this->useCampaigns() ) {
            try {
                return $this->sdk->queryAssets();
            } catch ( \Exception $e ) {
                $this::captureException( $e );
                return [];
            }
        }
        return [];
    }

    public function assignContentCampaignAsset( $post_id, $campaign_asset_guid ) {
        if ( $this->useCampaigns() ) {
            $post = get_post( $post_id );
            if ( $post == null || $post->post_type != 'post' ) {
                return;
            }
            if ( $campaign_asset_guid ) {
                update_post_meta( $post_id, CAMPAIGN_ASSET_META_KEY, $campaign_asset_guid );
            } else {
                delete_post_meta( $post_id, CAMPAIGN_ASSET_META_KEY );
            }
        }
    }

    public function log( string $level, string $message, array $context = [] ) {
        if ( ! class_exists( '\WP_CLI' ) ) {
            return;
        }

        if ( $context ) {
            $message = $message . ' | ' . json_encode( $context );
        }

        switch ( $level ) {
            case LogLevel::WARNING:
                return \WP_CLI::warning( $message );
            case LogLevel::INFO:
                return \WP_CLI::log( $message );
            case LogLevel::DEBUG:
                return \WP_CLI::debug( $message, 'organic' );
            default:
                $this->warning( 'Unknown log-level', [ 'level' => $level ] );
                $this->info( $message, $context );
                return;
        }
    }

    public function warning( string $message, array $context = [] ) {
        $this->log( LogLevel::WARNING, $message, $context );
    }

    public function info( string $message, array $context = [] ) {
        $this->log( LogLevel::INFO, $message, $context );
    }

    public function debug( string $message, array $context = [] ) {
        $this->log( LogLevel::DEBUG, $message, $context );
    }

    public function getSdkVersion() {
        return $this->sdk::SDK_V2;
    }

    public function getSdkUrl( string $type = 'default' ) {
        return $this->sdk->getSdkV2Url( $type );
    }

    public function getCustomCSSUrl() {
        return $this->getRestAPIUrl() . '/sdk/customcss/' . $this->getSiteId();
    }

    public function getPlatformUrl() {
        $organic_app_url = getenv( 'ORGANIC_PLATFORM_URL' );
        if ( ! $organic_app_url ) {
            $organic_app_url = self::DEFAULT_PLATFORM_URL;
        }
        return $organic_app_url;
    }

    public function getRestAPIUrl() {
        $organic_api_url = getenv( 'ORGANIC_API_URL_REST' );
        if ( ! $organic_api_url ) {
            $organic_api_url = self::DEFAULT_REST_API_URL;
        }
        return $organic_api_url;
    }
}
