<?php

namespace Organic;

class AdminSettings {

    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;

        add_filter( 'plugin_action_links_organic/organic.php', [ $this, 'pluginSettingsLink' ] );
        add_action( 'admin_menu', [ $this, 'adminMenu' ] );
        add_action( 'admin_print_scripts', [ $this, 'adminInlineJS' ] );
        add_action( 'admin_print_styles', [ $this, 'adminInlineCSS' ] );
    }

    public function adminSettings() {
        if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
            try {
                $this->handlePostRequest();
            } catch ( \Exception $e ) {
                echo esc_html( 'Error: ' . $e->getMessage() );
                echo esc_html( $e->getTraceAsString() );
            }
        }

        $this->showSettings();
    }

    protected function handlePostRequest() {
        if ( ! check_admin_referer( 'organic_settings_nonce' ) ) {
            AdminNotice::error( 'Unable to verify request, please reload page and try again' );
            return;
        }

        switch ( $_POST['organic_action'] ) {
            case 'organic_update_settings':
                $this->updateSettings();
                AdminNotice::success( 'Settings saved' );
                break;

            case 'organic_content_sync':
                $count = $this->organic->syncContent( 100 );
                AdminNotice::success( 'Synced ' . $count . ' posts' );
                break;

            case 'organic_content_id_sync':
                $stats = $this->organic->syncContentIdMap();
                AdminNotice::success( 'Content Id-map synced | ' . json_encode( $stats ) );
                break;

            case 'organic_category_sync':
                $this->organic->syncCategories();
                AdminNotice::success( 'Categories synced' );
                break;

            case 'organic_force_pull_configs':
                $this->organic->syncPluginConfig();
                $this->organic->syncAdsTxt();
                $this->organic->syncAdConfig();
                $this->organic->syncAffiliateConfig();
                AdminNotice::success( 'Pulled latest configs' );
                break;

            default:
                AdminNotice::warning( 'Unknown operation.' );
        }
    }

    protected function updateSettings() {
        if ( ! check_admin_referer( 'organic_settings_nonce' ) ) {
            return;
        }

        // Organic Settings
        $this->organic->updateOption(
            'organic::enabled',
            isset( $_POST['organic_enabled'] ) ? true : false,
            false
        );
        $this->organic->updateOption(
            'organic::test_mode',
            isset( $_POST['organic_test_mode'] ) ? true : false,
            false
        );
        $this->organic->updateOption(
            'organic::percent_test',
            sanitize_text_field( $_POST['organic_percent'] ),
            false
        );
        $this->organic->updateOption(
            'organic::test_value',
            sanitize_text_field( $_POST['organic_value'] ),
            false
        );

        // General Settings
        $this->organic->updateOption(
            'organic::sdk_key',
            sanitize_text_field( $_POST['organic_sdk_key'] ) ?: '',
            false
        );
        $this->organic->sdk->updateToken( sanitize_text_field( $_POST['organic_sdk_key'] ) );
        $this->organic->updateOption(
            'organic::site_id',
            sanitize_text_field( $_POST['organic_site_id'] ) ?: '',
            false
        );
        $this->organic->updateOption(
            'organic::cmp',
            sanitize_text_field( $_POST['organic_cmp'] ) ?: '',
            false
        );
        $this->organic->updateOption(
            'organic::one_trust_id',
            sanitize_text_field( $_POST['organic_one_trust_id'] ) ?: '',
            false
        );
        $this->organic->updateOption(
            'organic::amp_ads_enabled',
            isset( $_POST['organic_amp_ads_enabled'] ) ? true : false,
            false
        );
        $this->organic->updateOption(
            'organic::ad_slots_prefill_enabled',
            isset( $_POST['organic_ad_slots_prefill_enabled'] ) ? true : false,
            false
        );
        $this->organic->updateOption(
            'organic::log_to_sentry',
            isset( $_POST['organic_log_to_sentry'] ) ? true : false,
            false
        );

        // Organic Affiliate
        $this->organic->updateOption(
            'organic::affiliate_enabled',
            isset( $_POST['organic_affiliate_enabled'] ) ? true : false,
            false
        );

        // Organic Campaigns
        $this->organic->updateOption(
            'organic::campaigns_enabled',
            isset( $_POST['organic_campaigns_enabled'] ) ? true : false,
            false
        );

        // Organic Ads
        $this->organic->updateOption(
            'organic::feed_images',
            isset( $_POST['organic_feed_images'] ) ? true : false,
            false
        );

        $val = isset( $_POST['organic_enable_ads_txt_redirect'] );
        $this->organic->getAdsTxtManager()->enableAdsTxtRedirect( $val );

        $val = array_map( 'sanitize_text_field', $_POST['organic_post_types'] );
        $this->organic->updateOption(
            'organic::post_types',
            $val,
            false
        );
        $this->organic->setPostTypes( $val );

        $this->organic->updateOption(
            'organic::content_foreground',
            isset( $_POST['organic_content_foreground'] ) ? true : false,
            false
        );

    }

    public function showSettings() {
        $enabled = $this->organic->getOption( 'organic::enabled' );
        $test_mode = $this->organic->getOption( 'organic::test_mode' );
        $organic_test = $this->organic->getOption( 'organic::percent_test' );
        $organic_value = $this->organic->getOption( 'organic::test_value' );

        $sdk_key = $this->organic->getOption( 'organic::sdk_key' );
        $site_id = $this->organic->getOption( 'organic::site_id' );
        $cmp = $this->organic->getOption( 'organic::cmp' );
        $one_trust_id = $this->organic->getOption( 'organic::one_trust_id' );
        $amp_ads_enabled = $this->organic->getOption( 'organic::amp_ads_enabled' );
        $ad_slots_prefill_enabled = $this->organic->getOption( 'organic::ad_slots_prefill_enabled' );
        $log_to_sentry = $this->organic->getOption( 'organic::log_to_sentry', true );

        $affiliate_enabled = $this->organic->getOption( 'organic::affiliate_enabled' );

        $campaigns_enabled = $this->organic->getOption( 'organic::campaigns_enabled' );

        $feed_images = $this->organic->getOption( 'organic::feed_images' );
        $ads_txt_redirect = $this->organic->getOption( 'organic::ads_txt_redirect_enabled' );
        $content_foreground = $this->organic->getOption( 'organic::content_foreground' );

        $ads_txt = $this->organic->getOption( 'organic::ads_txt' );

        $settings_last_updated = $this->organic->settingsLastUpdated();

        $total_published_posts = $this->organic->buildQuerySyncablePosts( 1 )->found_posts;
        $total_synced_posts = $this->organic->buildQueryNewlyUnsyncedPosts( 1 )->found_posts;

        $ad_config = $this->organic->getAdsConfig();
        $amp_config = $this->organic->getAmpConfig();
        $prefill_config = $this->organic->getPrefillConfig();
        $affiliate_config = [ 'publicDomain' => $this->organic->getAffiliateDomain() ];

        $contentSyncCron = \DateTimeImmutable::createFromFormat( 'U', wp_next_scheduled( 'organic_cron_sync_content' ), wp_timezone() );
        ?>
        <div id="organic-settings-page" class="wrap">
            <div id="organic-notices">
                <?php AdminNotice::showNotices(); ?>
            </div>
            <h1>Organic Settings</h1>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings_nonce' ); ?>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_enabled"
                            <?php echo $enabled ? 'checked' : ''; ?>
                        >
                        Organic Integration Enabled
                    </label>
                </p>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_test_mode"
                            id="organic-test-mode"
                            <?php echo $test_mode ? 'checked' : ''; ?>
                        />
                        Split Testing Enabled
                    </label>
                </p>
                <div id="organic-splittest-config" class="<?php echo ( ! $test_mode ? 'organic-hidden' : '' ); ?>">
                    <p>
                        <label>
                            % of traffic on Organic Browser SDK:
                            <input
                                type="text"
                                name="organic_percent"
                                value="<?php echo esc_attr( $organic_test ); ?>"
                            />
                        </label>
                    </p>
                    <p>
                        <label>
                            key for Split Test:
                            <input
                                type="text"
                                name="organic_value"
                                value="<?php echo esc_attr( $organic_value ); ?>"
                            />
                        </label>
                    </p>
                </div>

                <hr />
                <h2>General Settings</h2>
                <p>
                    <label>
                        Organic API Key:
                        <input
                            type="text"
                            name="organic_sdk_key"
                            class="organic-wide-input"
                            value="<?php echo esc_attr( $sdk_key ); ?>"
                        />
                    </label>
                </p>
                <p>
                    <label>
                        Organic Site ID:
                        <input
                            type="text"
                            name="organic_site_id"
                            class="organic-wide-input"
                            value="<?php echo esc_attr( $site_id ); ?>"
                        />
                    </label>
                </p>
                <p>
                    <label>
                        Consent Management Platform:
                        <select id="organic-cmp" name="organic_cmp">
                            <option value="">None</option>
                            <option value="built-in" <?php echo esc_html( ( $cmp == 'built-in' ? 'selected="selected"' : '' ) ); ?>>Built In</option>
                            <option value="one-trust" <?php echo esc_html( ( $cmp == 'one-trust' ? 'selected="selected"' : '' ) ); ?>>One Trust</option>
                        </select>
                    </label>
                </p>
                <p id="one-trust-config" class="<?php echo ( $cmp != 'one-trust' ? 'organic-hidden' : '' ); ?>">
                    <label>
                        One Trust ID:
                        <input
                            type="text"
                            name="organic_one_trust_id"
                            class="organic-wide-input"
                            value="<?php echo esc_attr( $one_trust_id ); ?>"
                        />
                    </label>
                </p>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_amp_ads_enabled"
                            <?php echo $amp_ads_enabled ? 'checked' : ''; ?>
                        />
                        AMP Integration Enabled
                    </label>
                </p>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_ad_slots_prefill_enabled"
                            <?php echo $ad_slots_prefill_enabled ? 'checked' : ''; ?>
                        />
                        Prefill Containers Enabled
                    </label>
                </p>
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="organic_log_to_sentry"
                            <?php echo $log_to_sentry ? 'checked' : ''; ?>
                        />
                        Automatically send plugin errors to Organic
                    </label>
                </p>

                <hr />
                <h2>Organic Affiliate</h2>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_affiliate_enabled"
                            <?php echo $affiliate_enabled ? 'checked' : ''; ?>
                        />
                        Organic Affiliate Enabled
                    </label>
                </p>

                <hr />
                <h2>Organic Campaigns</h2>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_campaigns_enabled"
                            <?php echo $campaigns_enabled ? 'checked' : ''; ?>
                        />
                        Organic Campaigns Enabled
                    </label>
                </p>

                <hr />
                <h2>Organic Ads</h2>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_feed_images"
                            <?php echo $feed_images ? 'checked' : ''; ?>
                        />
                        Inject Images into RSS Feed (for Connatix Playspace player)
                    </label>
                </p>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_enable_ads_txt_redirect"
                            <?php echo $ads_txt_redirect ? 'checked' : ''; ?>
                        />
                        Ads.txt Redirect Enabled
                    </label>
                </p>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="organic_content_foreground"
                            <?php echo $content_foreground ? 'checked' : ''; ?>
                        />
                        Force content sync on Save (use only if CRON is disabled on your site)
                    </label>
                </p>
                <fieldset>
                    <p>
                        Which post types from your CMS should be treated as content for synchronization with
                        the Organic Platform and as eligible for the Organic SDK to be loaded on?
                        <ul>
                            <?php $this->injectPostTypesList(); ?>
                        </ul>
                    </p>

                </fieldset>

                <p>
                    <button type="submit" name="organic_action" value="organic_update_settings">
                        Save settings
                    </button>
                </p>
                <p>Plugin settings last updated: <?php echo esc_attr( $settings_last_updated->format( 'y-m-d h:i:s T' ) ); ?></p>
            </form>

            <hr />
            <h1>Status and Actions</h1>
            <h2>Content Sync</h2>
            <p>Known Posts: <?php echo number_format( $total_published_posts ); ?></p>
            <p>Recently Updated Posts (unsynced): <?php echo number_format( $total_synced_posts ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings_nonce' ); ?>
                <p>
                    <button type="submit" name="organic_action" value="organic_content_sync">
                        Force-push Content Batch to the Organic Platform
                    </button>
                </p>
                <p>
                    <button type="submit" name="organic_action" value="organic_category_sync">
                        Force-push Categories to the Organic Platform
                    </button>
                </p>
                <p>
                    <button type="submit" name="organic_action" value="organic_content_id_sync">
                        Force-sync Content IDs to the Organic Platform
                    </button>
                </p>
            </form>

            <hr />
            <h2>Current configs pulled from Organic Platform</h2>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings_nonce' ); ?>
                <button type="submit" name="organic_action" value="organic_force_pull_configs">
                    Force-pull latest configs from Organic Platform
                </button>
            </form>
            <p>
                <label>
                    <input
                        type="checkbox"
                        name="organic_show_debug_info"
                        id="organic-show-debug-info"
                    />
                    Show debug info below
                </label>
            </p>
            <div id="organic-debug-info" class="organic-hidden">
                <?php $this->injectEnvInfo( 'VERSION', $this->organic->version ); ?>
                <?php $this->injectEnvInfo( 'ENVIRONMENT', $this->organic->getEnvironment() ); ?>
                <?php $this->injectEnvInfo( 'API_URL', $this->organic->sdk->getAPIUrl() ); ?>
                <?php $this->injectEnvInfo( 'CDN_URL', $this->organic->sdk->getCDNUrl() ); ?>
                <?php $this->injectEnvInfo( 'SDK_URL', $this->organic->getSdkUrl() ); ?>
                <?php $this->injectEnvInfo( 'PREBID_URL', $this->organic->getAdsConfig()->getPrebidBuildUrl() ); ?>
                <?php $this->injectEnvInfo( 'PLATFORM_URL', $this->organic->getPlatformUrl() ); ?>
                <?php $this->injectEnvInfo( 'ADS_TXT_URL', $this->organic->getAdsTxtManager()->getAdsTxtUrl() ); ?>
                <?php $this->injectEnvInfo( 'Next Content Sync', $contentSyncCron ? $contentSyncCron->format( DATE_ATOM ) : 'false' ); ?>
                <?php $this->injectEnvInfo( 'Content Re-Sync Triggered At', $this->organic->getContentResyncStartedAt()->format( DATE_ATOM ) ); ?>
                <?php if ( ! $ads_txt_redirect ) { ?>
                    <p>
                        <label>
                            Ads.txt
                            <textarea
                                    class="organic-debug-textarea"
                                    readonly
                            ><?php echo esc_textarea( $ads_txt ); ?></textarea>
                        </label>
                    </p>
                <?php } ?>
                <?php $this->injectConfigInfo( 'AdConfig', $ad_config ); ?>
                <?php $this->injectConfigInfo( 'AmpConfig', $amp_config ); ?>
                <?php $this->injectConfigInfo( 'PrefillConfig', $prefill_config ); ?>
                <?php $this->injectConfigInfo( 'AffiliateConfig', $affiliate_config ); ?>
            </div>

        </div>
        <?php
    }

    public function injectEnvInfo( $key, $value ) {
        ?>
        <p>
            <?php echo esc_html( $key . ': ' . $value ); ?>
        </p>
        <?php
    }

    public function injectConfigInfo( $label, $infoObj ) {
        $info = json_encode( $infoObj, JSON_PRETTY_PRINT );
        ?>
        <p>
            <label>
                <?php echo esc_html( $label ); ?>
                <textarea
                    class="organic-debug-textarea"
                    readonly
                ><?php echo esc_textarea( $info ); ?></textarea>
            </label>
        </p>
        <?php
    }

    public function injectPostTypesList() {
        $post_types = get_post_types(
            [
                'public'   => true,
                '_builtin' => false,
            ]
        );
        $post_types[] = 'post';
        $post_types[] = 'page';

        foreach ( $post_types as $post_type ) {
            $checked = '';
            if ( in_array( $post_type, $this->organic->getPostTypes() ) ) {
                $checked = 'checked';
            }
            ?>
            <li>
                <label>
                    <input
                        type="checkbox"
                        name="organic_post_types[]"
                        value="<?php echo esc_attr( $post_type ); ?>"
                        <?php echo esc_attr( $checked ); ?>
                    />
                    <?php echo esc_html( $post_type ); ?>
                </label>
            </li>
            <?php
        }
    }

    public function adminInlineJS() {
        $screen = get_current_screen();
        if ( empty( $screen ) || ( ! str_contains( $screen->id, 'Organic/AdminSettings' ) ) ) {
            return;
        }

        ?>
        <script>
            (function (){
                'use strict';
                document.addEventListener('DOMContentLoaded', function () {
                    // Split testing
                    var splitTestCheckbox = document.getElementById('organic-test-mode');
                    var splitTestConfig = document.getElementById('organic-splittest-config');
                    splitTestCheckbox.addEventListener('change', function() {
                        if (!splitTestCheckbox.checked) {
                            splitTestConfig.classList.add('organic-hidden');
                        } else {
                            splitTestConfig.classList.remove('organic-hidden');
                        }
                    });

                    // OneTrust
                    var oneTrustSelector = document.getElementById('organic-cmp');
                    var oneTrustConfig = document.getElementById('one-trust-config');
                    oneTrustSelector.addEventListener('change', function() {
                        var isOneTrust = oneTrustSelector.value === 'one-trust';
                        if (!isOneTrust) {
                            oneTrustConfig.classList.add('organic-hidden');
                        } else {
                            oneTrustConfig.classList.remove('organic-hidden');
                        }
                    });

                    // Debug info
                    var debugInfoCheckbox = document.getElementById('organic-show-debug-info');
                    var debugInfo = document.getElementById('organic-debug-info');
                    debugInfoCheckbox.addEventListener('change', function() {
                        if (!debugInfoCheckbox.checked) {
                            debugInfo.classList.add('organic-hidden');
                        } else {
                            debugInfo.classList.remove('organic-hidden');
                        }
                    });
                });
            }());
        </script>
        <?php
    }

    public function adminInlineCSS() {
        $screen = get_current_screen();
        if ( empty( $screen ) || ( ! str_contains( $screen->id, 'Organic/AdminSettings' ) ) ) {
            return;
        }

        ?>
        <style>
            #organic-settings-page .organic-wide-input {
                width: 355px;
            }

            #organic-settings-page .organic-debug-textarea {
                width:650px;
                height: 500px;
                display: block;
            }

            #organic-settings-page .organic-hidden {
                display: none;
            }
        </style>
        <?php
    }

    public function adminMenu() {
        add_submenu_page(
            'options-general.php',          // Slug of parent.
            'Organic Settings',             // Page Title.
            'Organic Settings ',            // Menu title.
            'manage_options',               // Capability.
            __FILE__,                       // Slug.
            [ $this, 'adminSettings' ] // Function to call.
        );
    }

    public function pluginSettingsLink( $links ) {
        // Build and escape the URL.
        $url = esc_url(
            add_query_arg(
                'page',
                'organic/Organic/AdminSettings.php',
                get_admin_url() . 'admin.php'
            )
        );

        // Create the link.
        $settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';

        // Adds the link to the end of the array.
        array_push( $links, $settings_link );
        return $links;
    }
}
