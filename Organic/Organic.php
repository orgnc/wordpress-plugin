<?php

namespace Organic;

use DateTime;
use GuzzleHttp\Exception\GuzzleException;
use Organic\SDK\OrganicSdk;
use Exception;
use WP_Query;

use function \get_user_by;
use function Sentry\captureException;

const CAMPAIGN_ASSET_META_KEY = 'empire_campaign_asset_guid';
const GAM_ID_META_KEY = 'empire_gam_id';
const SYNC_META_KEY = 'empire_sync';


/**
 * Client Plugin for the Organic Platform
 */
class Organic {

    const DEFAULT_PLATFORM_URL = 'https://app.organic.ly';

    private $isEnabled = false;

    /**
     * @var bool True if we need to load via JavaScript
     */
    private $isTestMode = false;

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
     * @var int % of traffic to send to Organic SDK instead of Organic Pixel
     */
    private $organicPixelTestPercent = 0;

    /**
     * @var string|null Name of the split test to use (must be tied to GAM value in 'tests' key)
     */
    private $organicPixelTestValue = null;

    /**
     * @var string Organic environment
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
     * @var ConnatixConfig
     */
    private $connatixConfig = null;

    /**
     * @var FbiaConfig Configuration for FBIA
     */
    private $fbiaConfig = null;

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
     *       +"ampAdsEnabled": "1",
     *       +"injectAdsConfig": "1",
     *       +"adSlotsPrefillEnabled": "1",
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
    public function __construct( string $environment ) {
        $this->environment = $environment;
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
     * @return void
     */
    public function getOption( $name, $default = false ) {
        if ( function_exists( 'get_option' ) ) {
            $result = get_option( $name );

            // Fallback to old version if it exists instead
            if ( ! $result ) {
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

            return update_option( $name, $value, $autoload );
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
        $this->isTestMode = $this->getOption( 'organic::test_mode' );
        $this->ampAdsEnabled = $this->getOption( 'organic::amp_ads_enabled' );
        $this->injectAdsConfig = $this->getOption( 'organic::inject_ads_config' );
        $this->adSlotsPrefillEnabled = $this->getOption( 'organic::ad_slots_prefill_enabled' );
        $this->cmp = $this->getOption( 'organic::cmp' );
        $this->oneTrustId = $this->getOption( 'organic::one_trust_id' );
        $this->organicPixelTestPercent = intval( $this->getOption( 'organic::percent_test' ) );
        $this->organicPixelTestValue = $this->getOption( 'organic::test_value' );

        $this->feedImages = $this->getOption( 'organic::feed_images' );

        $this->pixelId = $this->getOption( 'organic::pixel_id' );
        $this->pixelPublishedUrl = $this->getOption( 'organic::pixel_published_url' );
        $this->pixelTestingUrl = $this->getOption( 'organic::pixel_testing_url' );

        $this->postTypes = $this->getOption( 'organic::post_types', [ 'post', 'page' ] );

        $this->campaignsEnabled = $this->getOption( 'organic::campaigns_enabled' );
        $this->contentForeground = $this->getOption( 'organic::content_foreground' );

        $this->affiliateEnabled = $this->getOption( 'organic::affiliate_enabled' );
        $this->affiliateDomain = $this->getOption( 'organic::affiliate_domain' );

        /* Load up our sub-page configs */
        new AdminSettings( $this );
        new CCPAPage( $this );
        new PageInjection( $this );
        new ContentSyncCommand( $this );
        new ContentIdMapSyncCommand( $this );
        new AdConfigSyncCommand( $this );
        new AdsTxtSyncCommand( $this );
        new AffiliateConfigSyncCommand( $this );

        // Set up our GraphQL hooks to expose settings
        $graphql = new GraphQL( $this );
        $graphql->init();

        // Set up affiliate app
        if ( $this->isAffiliateAppEnabled() ) {
            new Affiliate( $this );
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
     * Returns true if we need to load the SDK via JavaScript for a split test
     *
     * @return bool
     */
    public function isTestMode() : bool {
        return $this->isTestMode;
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

    /**
     * Returns if Campaigns app is enabled
     *
     * @return bool
     */
    public function isCampaignsAppEnabled() {
        return $this->isEnabled() && $this->campaignsEnabled;
    }

    /**
     * Returns if Affiliate app is enabled
     *
     * @return bool
    */
    public function isAffiliateAppEnabled() {
        return $this->isEnabled() && $this->getSdkVersion() == $this->sdk::SDK_V2 && $this->affiliateEnabled;
    }

    /**
     * Check if the AMP Ads are configured and enabled
     *
     * @return bool
     */
    public function useAmpAds() : bool {
        return $this->isEnabled() && $this->ampAdsEnabled;
    }

    public function useInjectedAdsConfig() : bool {
        return $this->isEnabled() && $this->injectAdsConfig;
    }

    public function useAdsSlotsPrefill() : bool {
        return $this->isEnabled() && $this->adSlotsPrefillEnabled;
    }

    public function getAffiliateDomain() {
        return $this->affiliateDomain;
    }

    /**
     * @param $content string|null
     * @return bool|int
     */
    public function eligibleForAds( $content = null ) {
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

    public function getFbiaConfig() : FbiaConfig {
        if ( ! empty( $this->fbiaConfig ) ) {
            return $this->fbiaConfig;
        }

        $raw = (array) $this->getOption( 'organic::ad_fbia_config', [] );
        $this->fbiaConfig = new FbiaConfig( $raw );

        return $this->fbiaConfig;
    }

    public function getAdsConfig() : AdsConfig {
        if ( ! empty( $this->adsConfig ) ) {
            return $this->adsConfig;
        }

        $rawAdsConfig = $this->getOption( 'organic::ad_settings', [] );
        $this->adsConfig = new AdsConfig( $rawAdsConfig );

        return $this->adsConfig;
    }

    public function getConnatixConfig() : ConnatixConfig {
        if ( ! empty( $this->connatixConfig ) ) {
            return $this->connatixConfig;
        }

        $this->connatixConfig = new ConnatixConfig( $this );

        return $this->connatixConfig;
    }


    public function getCurrentUrl() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
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

        $url = $this->getCurrentUrl();
        $keywords = $this->getTagsFor( $post->ID );
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

        $categories = get_terms( [ 'category' ] );
        $cat_id_map = array();
        $trees = array();
        foreach ( $categories as $cat ) {
            $cat_id_map[ $cat->term_id ] = array(
                'externalId' => (string) $cat->term_id,
                'name' => $cat->name,
                'children' => array(),
            );
        }
        foreach ( $categories as &$cat ) {
            $cat_data = &$cat_id_map[ $cat->term_id ];
            if ( $cat->parent == 0 ) {
                array_push( $trees, $cat_data );
            } else {
                $parent = &$cat_id_map[ $cat->parent ];
                $children = &$parent['children'];
                array_push( $children, $cat_data );
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
     * @param $post
     * @return void|null
     */
    public function syncPost( $post ) {
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

        $canonical = get_permalink( $post->ID );

        # In order to support non-standard post metadata, we have a filter for each attribute
        $external_id = \apply_filters( 'organic_post_id', $post->ID );
        $canonical = \apply_filters( 'organic_post_url', $canonical, $post->ID );
        $title = \htmlspecialchars_decode( $post->post_title );
        $title = \apply_filters( 'organic_post_title', $title, $post->ID );
        $subtitle = \htmlspecialchars_decode( $post->post_subtitle );
        $subtitle = \apply_filters( 'organic_post_subtitle', $subtitle, $post->ID );
        $featured_image_url = \apply_filters( 'organic_post_featured_image_url', $post->post_featured_image_url, $post->ID );
        $template_name = \apply_filters( 'organic_post_template_name', $post->post_template_name, $post->ID );
        $sponsorship = \apply_filters( 'organic_post_sponsorship', $post->post_sponsorship, $post->ID );
        $content = \apply_filters( 'organic_post_content', $post->post_content, $post->ID );
        $is_published = \apply_filters( 'organic_post_is_published', $post->post_is_published, $post->ID );
        $published_date = \apply_filters( 'organic_post_publish_date', $post->post_date, $post->ID );
        $modified_date = \apply_filters( 'organic_post_modified_date', $post->post_modified, $post->ID );
        $campaign_asset_guid = null;
        if ( $this->isCampaignsAppEnabled() ) {
            $campaign_asset_guid = get_post_meta( $post->ID, CAMPAIGN_ASSET_META_KEY, true );
            if ( $campaign_asset_guid == '' ) {
                $campaign_asset_guid = null;
            }
        }

        $authors = array();

        // Assume the default Wordpress author structure
        if ( $post->post_author ) {
            $user = get_user_by( 'id', $post->post_author );
            if ( $user ) {
                $authors[] = array(
                    'externalId' => (string) $post->post_author,
                    'name' => $user->display_name,
                );
            }
        }
        $authors = \apply_filters( 'organic_post_authors', $authors, $post->ID );

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

        $third_party_integrations = array();
        if ( function_exists( 'wp_get_post_get_third_party_integrations' ) ) {
            foreach ( wp_get_post_get_third_party_integrations( $post->ID ) as $third_party_integration_id ) {
                $third_party_integration = get_third_party_integration( $third_party_integration_id );
                $third_party_integrations[] = array(
                    'externalId' => (string) $third_party_integration->term_id,
                    'site_guid' => $third_party_integration->site_guid,
                    'has_google_analytics' => $third_party_integration->has_google_analytics,
                    'has_google_tag_manager' => $third_party_integration->has_google_tag_manager,
                    'has_facebook_targeting_pixel' => $third_party_integration->has_facebook_targeting_pixel,
                    'has_google_ads_targeting_pixel' => $third_party_integration->has_google_ads_targeting_pixel,
                    'has_openweb' => $third_party_integration->has_openweb,
                );
            }
        }
        $third_party_integrations =
            \apply_filters( 'organic_post_third_party_integrations', $third_party_integrations, $post->ID );

        $seo_schema_tags = array();
        if ( function_exists( 'wp_get_post_get_seo_schema_tags' ) ) {
            foreach ( wp_get_post_get_seo_schema_tags( $post->ID ) as $seo_schema_tag_id ) {
                $seo_schema_tag = get_seo_schema_tag( $seo_schema_tag_id );
                $seo_schema_tags[] = array(
                    'externalId' => (string) $seo_schema_tag->term_id,
                    'site_guid' => $seo_schema_tag->site_guid,
                    'schema_type' => (string) $seo_schema_tag->schema_type,
                    'schema_content' => (string) $seo_schema_tag->schema_content,
                );
            }
        }
        $seo_schema_tags =
            \apply_filters( 'organic_post_seo_schema_tags', $seo_schema_tags, $post->ID );

        $seo_data = array();
        if ( function_exists( 'wp_get_post_get_seo_data' ) ) {
            foreach ( wp_get_post_get_seo_data( $post->ID ) as $seo_datapoint_id ) {
                $seo_datapoint = get_seo_datapoint( $seo_datapoint_id );
                $seo_data[] = array(
                    'externalId' => (string) $seo_datapoint->term_id,
                    'site_guid' => $seo_datapoint->site_guid,
                    'seo_title' => (string) $seo_datapoint->seo_title,
                    'seo_description' => (string) $seo_datapoint->seo_description,
                    'seo_image_url' => (string) $seo_datapoint->seo_image_url,
                );
            }
        }
        $seo_data = \apply_filters( 'organic_post_seo_data', $seo_data, $post->ID );

        $custom_metadata = array();
        if ( function_exists( 'wp_get_post_get_custom_metadata' ) ) {
            foreach ( wp_get_post_get_custom_metadata( $post->ID ) as $custom_metadatapoint_id ) {
                $custom_metadatapoint = get_custom_metadatapoint( $custom_metadatapoint_id );
                $custom_metadata[] = array(
                    'externalId' => (string) $custom_metadatapoint->term_id,
                    'site_guid' => $custom_metadatapoint->site_guid,
                    'twitter_meta' => (string) $custom_metadatapoint->twitter_meta,
                    'opengraph_meta' => (string) $custom_metadatapoint->opengraph_meta,
                );
            }
        }
        $custom_metadata = \apply_filters( 'organic_post_custom_metadata', $custom_metadata, $post->ID );

        $meta_tags = array();
        if ( function_exists( 'wp_get_post_get_meta_tag' ) ) {
            foreach ( wp_get_post_get_meta_tag( $post->ID ) as $meta_tag_id ) {
                $meta_tag = get_meta_tag( $meta_tag_id );
                $meta_tags[] = array(
                    'externalId' => (string) $meta_tag->term_id,
                    'site_guid' => $meta_tag->site_guid,
                    'schema_type' => (string) $meta_tag->schema_type,
                    'schema_content' => (string) $meta_tag->schema_content,
                );
            }
        }
        $meta_tags = \apply_filters( 'organic_post_meta_tags', $meta_tags, $post->ID );

        $rich_content_images = array();
        if ( function_exists( 'wp_get_post_get_rich_content_image' ) ) {
            foreach ( wp_get_post_get_rich_content_image( $post->ID ) as $rich_content_image_id ) {
                $rich_content_image = get_rich_content_image( $rich_content_image_id );
                $rich_content_images[] = array(
                    'externalId' => (string) $rich_content_image->term_id,
                    'site_guid' => $rich_content_image->site_guid,
                    'image_url' => (string) $rich_content_image->image_url,
                );
            }
        }
        $rich_content_images = \apply_filters( 'organic_post_rich_content_images', $rich_content_images, $post->ID );

        $rich_content_videos = array();
        if ( function_exists( 'wp_get_post_get_rich_content_video' ) ) {
            foreach ( wp_get_post_get_rich_content_video( $post->ID ) as $rich_content_video_id ) {
                $rich_content_video = get_rich_content_video( $rich_content_video_id );
                $rich_content_videos[] = array(
                    'externalId' => (string) $rich_content_video->term_id,
                    'site_guid' => $rich_content_video->site_guid,
                    'image_url' => (string) $rich_content_video->video_url,
                );
            }
        }
        $rich_content_videos = \apply_filters( 'organic_post_rich_content_videos', $rich_content_videos, $post->ID );

        $rich_content_embeds = array();
        if ( function_exists( 'wp_get_post_get_rich_content_embed' ) ) {
            foreach ( wp_get_post_get_rich_content_embed( $post->ID ) as $rich_content_embed_id ) {
                $rich_content_embed = get_rich_content_embed( $rich_content_embed_id );
                $rich_content_embeds[] = array(
                    'externalId' => (string) $rich_content_embed->term_id,
                    'site_guid' => $rich_content_embed->site_guid,
                    'image_url' => (string) $rich_content_embed->embed_url,
                );
            }
        }
        $rich_content_embeds = \apply_filters( 'organic_post_rich_content_embeds', $rich_content_embeds, $post->ID );

        try {
            $result = $this->sdk->contentCreateOrUpdate(
                $external_id,
                $canonical,
                $title,
                $subtitle,
                $featured_image_url,
                $template_name,
                $sponsorship,
                $is_published,
                new DateTime( $published_date ),
                new DateTime( $modified_date ),
                $content,
                $authors,
                $categories,
                $tags,
                $third_party_integrations,
                $seo_schema_tags,
                $seo_data,
                $custom_metadata,
                $meta_tags,
                $rich_content_images,
                $rich_content_videos,
                $rich_content_embeds,
                $campaign_asset_guid
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
     * @param $posts
     * @return int # of posts sync-ed
     * @throws Exception
     */
    private function _syncPosts( $posts ) {
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
        $args = array(
            'post_type' => $this->getPostTypes(),
            'post_status' => 'publish',
            'posts_per_page' => $batch,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
        );

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
            array(
                array(
                    'key' => SYNC_META_KEY,
                    'compare' => 'NOT EXISTS',
                ),
            )
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
            array(
                array(
                    'key' => SYNC_META_KEY,
                    'value' => 'synced',
                    'compare' => '!=',
                ),
            )
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
        $this->_syncPosts( $query->posts );

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

    public function syncAdConfig() {
        $config = $this->sdk->queryAdConfig();

        $this->debug( 'Got site domain: ' . $config['domain'] );
        $this->updateOption( 'organic::site_domain', $config['domain'], false );

        $this->debug( 'Got Ad Settings: ', $config['settings'] );
        $this->updateOption( 'organic::ad_settings', $config['settings'], false );

        $this->debug( 'Got Amp Config: ', $config['ampConfig'] );
        $this->updateOption( 'organic::ad_amp_config', $config['ampConfig'], false );

        $this->debug( 'Got Prefill Config: ', $config['prefillConfig'] );
        $this->updateOption( 'organic::ad_prefill_config', $config['prefillConfig'], false );

        $this->debug( 'Got FBIA Config: ', $config['fbiaConfig'] );
        $this->updateOption( 'organic::ad_fbia_config', $config['fbiaConfig'], false );

        return array(
            'updated' => true,
        );
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
            return array(
                'updated' => false,
            );
        }

        $domain = $config['publicDomain'];
        $this->debug( 'Got affiliate domain: ' . $domain );
        $this->updateOption( 'organic::affiliate_domain', $domain, false );

        return array(
            'updated' => true,
        );
    }

    public function substituteTags( string $content ) : string {
        global $post;

        // Check to see if the plugin is even turned on
        $enabled = $this->getOption( 'organic::enabled' );
        if ( ! $enabled ) {
            return $content;
        }

        if ( ! $post || ! is_object( $post ) ) {
            return $content;
        }

        // check if we have a tracking tag for this post
        $tag = get_post_meta( $post->ID, 'organic_tag', true );
        if ( ! $tag ) {
            return $content;
        }

        return $content;
    }

    public function getAdsTxtManager() : AdsTxt {
        return $this->adsTxt;
    }

    /**
     * Check if we are configured for foreground synchronization.
     * This does not block background / cron based synchronization as well, but may make your saves slower for
     * the editors.
     *
     * @return bool
     */
    public function getContentForeground() : bool {
        return $this->contentForeground;
    }

    /**
     * Indicates if the plugin is configured to inject Featured Images into the RSS feed
     *
     * @return bool
     */
    public function getFeedImages() : bool {
        return $this->feedImages;
    }

    /**
     * @return string|null
     */
    public function getPixelId() {
        return $this->pixelId;
    }

    /**
     * @return string|null
     */
    public function getPixelPublishedUrl() {
        return $this->pixelPublishedUrl;
    }

    /**
     * @return string|null
     */
    public function getPixelTestingUrl() {
        return $this->pixelTestingUrl;
    }

    /**
     * @param int|null $pixelId
     */
    public function setPixelId( $pixelId ) {
        $this->pixelId = $pixelId;
    }

    /**
     * @param string|null $pixelPublishedUrl
     */
    public function setPixelPublishedUrl( $pixelPublishedUrl ) {
        $this->pixelPublishedUrl = $pixelPublishedUrl;
    }

    /**
     * @param string|null $pixelTestingUrl
     */
    public function setPixelTestingUrl( $pixelTestingUrl ) {
        $this->pixelTestingUrl = $pixelTestingUrl;
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
    public function getOrganicPixelTestValue() {
        return $this->organicPixelTestValue;
    }

    /**
     * @return int
     */
    public function getOrganicPixelTestPercent(): int {
        return $this->organicPixelTestPercent;
    }

    public static function captureException( \Exception $e ) {
        if ( function_exists( '\Sentry\captureException' ) ) {
            \Sentry\captureException( $e );
            return;
        }

        error_log( $e->getMessage() );
    }

    public function loadCampaignsAssets() {
        if ( $this->isCampaignsAppEnabled() ) {
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
        if ( $this->isCampaignsAppEnabled() ) {
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
        return $this->getOption( 'organic::sdk_version', $this->sdk::SDK_V1 );
    }

    public function getPlatformUrl() {
        $organic_app_url = getenv( 'ORGANIC_PLATFORM_URL' );
        if ( ! $organic_app_url ) {
            $organic_app_url = self::DEFAULT_PLATFORM_URL;
        }
        return $organic_app_url;
    }
}
