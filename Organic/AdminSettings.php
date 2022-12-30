<?php

namespace Organic;

class AdminSettings {

    /**
     * @var Organic
     */
    private $organic;
    private $update_results = [];

    public function __construct( Organic $organic ) {
        $this->organic = $organic;

        add_filter( 'plugin_action_links_organic/organic.php', [ $this, 'pluginSettingsLink' ] );
        add_action( 'admin_menu', [ $this, 'adminMenu' ] );
        add_action( 'save_post', [ $this, 'handleSavePostHook' ], 10, 3 );
        add_action( 'admin_print_scripts', [ $this, 'adminInlineJS' ] );
        add_action( 'admin_print_styles', [ $this, 'adminInlineCSS' ] );
    }

    public function adminInlineJS() { ?>
        <script>
            (function (){
            'use strict';
            document.addEventListener('DOMContentLoaded', function () {
                // OneTrust
                var hideShowOneTrust = function () {
                    var isOneTrust = document.getElementById('organic_cmp').value === 'one-trust';
                    document.getElementById('one-trust-config').style.display = isOneTrust ? 'block' : 'none';
                };
                var oneTrustCheckbox = document.getElementById('organic_cmp');
                ['change', 'click', 'keypress'].forEach(function (event) {
                    oneTrustCheckbox.addEventListener(event, hideShowOneTrust);
                });

                // Connatix
                var hideShowConnatix = function() {
                    var checked = document.getElementById('organic_connatix_enabled').checked;
                    document.getElementById('connatix-config').style.display = checked ? 'block' : 'none';
                };
                var connatixCheckbox = document.getElementById('organic_connatix_enabled');
                ['change', 'click', 'keypress'].forEach(function (event) {
                    connatixCheckbox.addEventListener(event, hideShowConnatix);
                });

                // Ads Redirect
                var adsTxtCheckbox = document.getElementById('organic_enable_ads_txt_redirect');
                adsTxtCheckbox.addEventListener('change', function() {
                    var adsTxtContainer = document.getElementById('cont_organic_sync_ads_txt');
                    adsTxtContainer.style.display = adsTxtCheckbox.checked ? 'none' : 'block';
                });
            });
            }());
        </script>
        <?php
    }

    public function adminInlineCSS() {
        ?>
        <style>
            #organic_host {
                width: 400px;
            }
            .update_success {
                color: darkgreen;
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

    public function adminSettings() {
        // Save any setting updates
        if ( $_SERVER['REQUEST_METHOD'] == 'POST' && check_admin_referer( 'organic_settings' ) ) {
            if ( isset( $_POST['organic_sync_ads_txt'] ) ) {
                $this->organic->syncAdsTxt();
            } else if ( isset( $_POST['organic_ads_txt_redirect'] ) ) {
                $val = isset( $_POST['organic_enable_ads_txt_redirect'] );
                $this->organic->getAdsTxtManager()->enableAdsTxtRedirect( $val );
            } else if ( ! empty( $_POST['organic_post_types'] ) ) {
                $val = array_map( 'sanitize_text_field', $_POST['organic_post_types'] );
                $this->organic->updateOption(
                    'organic::post_types',
                    $val,
                    false
                );
                $this->organic->setPostTypes( $val );
            } else if ( isset( $_POST['organic_content_sync'] ) ) {
                try {
                    $this->organic->syncContent( 100 );
                } catch ( \Exception $e ) {
                    echo esc_html( 'Error: ' . $e->getMessage() );
                    echo esc_html( $e->getTraceAsString() );
                }
            } else if ( isset( $_POST['organic_content_id_sync'] ) ) {
                try {
                    $this->organic->syncContentIdMap();
                } catch ( \Exception $e ) {
                    echo esc_html( 'Error: ' . $e->getMessage() );
                    echo esc_html( $e->getTraceAsString() );
                }
            } else if ( isset( $_POST['organic_category_sync'] ) ) {
                try {
                    $this->organic->syncCategories();
                } catch ( \Exception $e ) {
                    echo esc_html( 'Error: ' . $e->getMessage() );
                    echo esc_html( $e->getTraceAsString() );
                }
            } else {
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
                    'organic::sdk_version',
                    sanitize_text_field( $_POST['organic_sdk_version'] ) ?: $this->organic->sdk::SDK_V1,
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
                $this->organic->updateOption(
                    'organic::connatix_enabled',
                    isset( $_POST['organic_connatix_enabled'] ) ? true : false,
                    false
                );
                $this->organic->updateOption(
                    'organic::connatix_playspace_id',
                    sanitize_text_field( $_POST['organic_connatix_playspace_id'] ),
                    false
                );
                $this->organic->updateOption(
                    'organic::feed_images',
                    isset( $_POST['organic_feed_images'] ) ? true : false,
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
                    'organic::sdk_key',
                    sanitize_text_field( $_POST['organic_sdk_key'] ) ?: '',
                    false
                );
                $this->organic->updateOption(
                    'organic::site_id',
                    sanitize_text_field( $_POST['organic_site_id'] ) ?: '',
                    false
                );
                $this->organic->updateOption(
                    'organic::amp_ads_enabled',
                    isset( $_POST['organic_amp_ads_enabled'] ) ? true : false,
                    false
                );
                $this->organic->updateOption(
                    'organic::inject_ads_config',
                    isset( $_POST['organic_inject_ads_config'] ) ? true : false,
                    false
                );
                $this->organic->updateOption(
                    'organic::ad_slots_prefill_enabled',
                    isset( $_POST['organic_ad_slots_prefill_enabled'] ) ? true : false,
                    false
                );
                $this->organic->updateOption(
                    'organic::campaigns_enabled',
                    isset( $_POST['organic_campaigns_enabled'] ) ? true : false,
                    false
                );
                $this->organic->updateOption(
                    'organic::content_foreground',
                    isset( $_POST['organic_content_foreground'] ) ? true : false,
                    false
                );
                $this->organic->updateOption(
                    'organic::affiliate_enabled',
                    isset( $_POST['organic_affiliate_enabled'] ) ? true : false,
                    false
                );
                $this->organic->sdk->updateToken( sanitize_text_field( $_POST['organic_sdk_key'] ) );
                $this->update_results[] = 'updated';
            }
        }

        $this->syncSettings();
        $this->showSettings();
        $this->showNotices();
    }

    protected function syncSettings() {
        if ( $_SERVER['REQUEST_METHOD'] != 'POST' || ! check_admin_referer( 'organic_settings' ) ) {
            return;
        }
        if ( ! isset( $_POST['organic_update'] ) ) {
            return;
        }

        switch ( $_POST['organic_update'] ) {
            case 'Update and sync':
                $result = $this->organic->syncAdConfig();
                if ( isset( $result['updated'] ) ) {
                    $this->update_results[] = 'synced';
                    AdminNotice::success( 'Updated and synced successfully.' );
                } else {
                    AdminNotice::warning( 'Updated but not synced.' );
                }
                break;

            default:
                AdminNotice::success( 'Updated successfully.' );
        }
    }

    protected function showFbiaNotices() {
        $fbia = $this->organic->getFbiaConfig();
        if ( ! $fbia->enabled || $fbia->isEmpty() ) {
            return;
        }

        if ( ! $fbia->isFacebookPluginInstalled() ) {
            AdminNotice::warning( 'Facebook instant articles are enabled for this site, but facebook plugin is not installed.' );
            return;
        }

        if ( ! $fbia->isFacebookPluginConfigured() ) {
            AdminNotice::warning(
                "Facebook instant articles are enabled for this site, but facebook plugin has its own ads 
                     configuration.
                     <br/>
                     Turn it off by setting 'Ad type' to 'none' on the plugin settings page."
            );
        }

    }

    protected function showNotices() {
        $this->showFbiaNotices();
        AdminNotice::showNotices();
    }

    public function showSettings() {
        $enabled = $this->organic->getOption( 'organic::enabled' );
        $test_mode = $this->organic->getOption( 'organic::test_mode' );
        $sdk_version = $this->organic->getSdkVersion();
        $connatix_enabled = $this->organic->getOption( 'organic::connatix_enabled' );
        $connatix_playspace_id = $this->organic->getOption( 'organic::connatix_playspace_id' );
        $feed_images = $this->organic->getOption( 'organic::feed_images' );
        $cmp = $this->organic->getOption( 'organic::cmp' );
        $one_trust_id = $this->organic->getOption( 'organic::one_trust_id' );
        $sdk_key = $this->organic->getOption( 'organic::sdk_key' );
        $site_id = $this->organic->getOption( 'organic::site_id' );
        $ads_txt = $this->organic->getOption( 'organic::ads_txt' );
        $ads_txt_redirect = $this->organic->getOption( 'organic::ads_txt_redirect_enabled' );
        $organic_test = $this->organic->getOption( 'organic::percent_test' );
        $organic_value = $this->organic->getOption( 'organic::test_value' );
        $amp_ads_enabled = $this->organic->getOption( 'organic::amp_ads_enabled' );
        $inject_ads_config = $this->organic->getOption( 'organic::inject_ads_config' );
        $ad_slots_prefill_enabled = $this->organic->getOption( 'organic::ad_slots_prefill_enabled' );
        $campaigns_enabled = $this->organic->getOption( 'organic::campaigns_enabled' );
        $content_foreground = $this->organic->getOption( 'organic::content_foreground' );
        $affiliate_enabled = $this->organic->getOption( 'organic::affiliate_enabled' );

        $total_published_posts = $this->organic->buildQuerySyncablePosts( 1 )->found_posts;
        $total_synced_posts = $this->organic->buildQueryNewlyUnsyncedPosts( 1 )->found_posts;
        if ( $this->update_results ) {
            $update_status = '<span class="update_success">' . join( ', ', $this->update_results ) . ' successfully </span>';
        } else {
            $update_status = '';
        }
        ?>
        <div class="wrap">
            <h2>Organic Settings</h2>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings' ); ?>
                <p><label><input type="checkbox" name="organic_enabled"
                                 id="organic_enabled" <?php echo $enabled ? 'checked' : ''; ?>> Organic Integration
                        Enabled</label></p>

                <hr />
                <h3>Organic Settings</h3>
                <p><label>SDK version:
                    <select id="organic_sdk_version" name="organic_sdk_version">
                        <option value="<?php echo esc_attr( $this->organic->sdk::SDK_V1 ); ?>" <?php echo esc_html( ( $sdk_version == $this->organic->sdk::SDK_V1 ? 'selected="selected"' : '' ) ); ?>>v1</option>
                        <option value="<?php echo esc_attr( $this->organic->sdk::SDK_V2 ); ?>" <?php echo esc_html( ( $sdk_version == $this->organic->sdk::SDK_V2 ? 'selected="selected"' : '' ) ); ?>>v2 (breaks ads)</option>
                    </select>
                </label></p>
                <p><label>Organic API Key: <input type="text" name="organic_sdk_key" style="width: 355px;" value="<?php echo esc_attr( $sdk_key ); ?>" /></label></p>
                <p><label>Organic Site ID: <input type="text" name="organic_site_id" style="width: 355px;" value="<?php echo esc_attr( $site_id ); ?>" /></label></p>
                <p><label>Consent Management:
                        <select id="organic_cmp" name="organic_cmp">
                            <option value="">None (WARNING: DO NOT USE IN PRODUCTION)</option>
                            <option value="built-in" <?php echo esc_html( ( $cmp == 'built-in' ? 'selected="selected"' : '' ) ); ?>>Built In</option>
                            <option value="one-trust" <?php echo esc_html( ( $cmp == 'one-trust' ? 'selected="selected"' : '' ) ); ?>>One Trust</option>
                        </select>
                    </label></p>
                <p id="one-trust-config" style="display: <?php echo ( $cmp == 'one-trust' ? 'block' : 'none' ); ?>;">
                    <label>One Trust ID: <input type="text" name="organic_one_trust_id" style="width: 355px;" value="<?php echo esc_attr( $one_trust_id ); ?>" /></label>
                </p>
                <p><label><input type="checkbox" name="organic_connatix_enabled"
                                 id="organic_connatix_enabled" <?php echo $connatix_enabled ? 'checked' : ''; ?>> Connatix Ads
                        Enabled</label></p>
                <p id="connatix-config" style="display: <?php echo esc_attr( $connatix_enabled ? 'block' : 'none' ); ?>;">
                    <label>Playspace Player ID: <input type="text" name="organic_connatix_playspace_id" style="width: 355px;" value="<?php echo esc_attr( $connatix_playspace_id ); ?>" /></label>
                </p>
                <p>
                    <label>Inject Images into RSS Feed: <input type="checkbox" name="organic_feed_images" <?php echo $feed_images ? 'checked' : ''; ?> /></label>
                </p>
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="organic_amp_ads_enabled"
                                id="organic_amp_ads_enabled"
                            <?php echo $amp_ads_enabled ? 'checked' : ''; ?>
                        />
                        AMP Ads Enabled
                    </label>
                </p>
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="organic_inject_ads_config"
                                id="organic_inject_ads_config"
                            <?php echo $inject_ads_config ? 'checked' : ''; ?>
                        />
                        Automatically inject ad configuration into the page
                        to increase page performance by reducing frontend requests
                    </label>
                </p>
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="organic_ad_slots_prefill_enabled"
                                id="organic_ad_slots_prefill_enabled"
                            <?php echo $ad_slots_prefill_enabled ? 'checked' : ''; ?>
                        />
                        Prefill ad containers to prevent Content Layout Shift (CLS) issues
                    </label>
                </p>
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="organic_campaigns_enabled"
                                id="organic_campaigns_enabled"
                            <?php echo $campaigns_enabled ? 'checked' : ''; ?>
                        />
                        Campaigns Application is enabled on the Platform
                    </label>
                </p>
                <label>
                    <input
                            type="checkbox"
                            name="organic_content_foreground"
                            id="organic_content_foreground"
                        <?php echo $content_foreground ? 'checked' : ''; ?>
                    />
                    Force content updates to happen immediately on save. Only use if CRON is disabled on your site.
                </label>
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="organic_affiliate_enabled"
                                id="organic_affiliate_enabled"
                        <?php echo $affiliate_enabled ? 'checked' : ''; ?>
                        <?php echo $sdk_version != $this->organic->sdk::SDK_V2 ? 'disabled' : ''; ?>
                        />
                        Affiliate Application is enabled on the Platform (Requires SDK V2)
                    </label>
                </p>

                <hr />
                <p><label><input type="checkbox" name="organic_test_mode"
                                 id="organic_test_mode" <?php echo $test_mode ? 'checked' : ''; ?>> Test Mode Enabled
                    </label></p>
                <p><label>% of ads on Organic Ads: <input type="text" name="organic_percent" id="organic_percent" value="<?php echo esc_attr( $organic_test ); ?>" /></label></p>
                <p><label>Key-Value for Split Test: <input type="text" name="organic_value" id="organic_value" value="<?php echo esc_attr( $organic_value ); ?>" /></label></p>

                <p>
                    <input id="update-submit" type="submit" name="organic_update" value="Update" />
                    &nbsp;
                    <input id="update-and-sync-submit" type="submit" name="organic_update" value="Update and sync" />
                    <?php echo esc_html( $update_status ); ?>
                </p>
            </form>

            <h2>Ads.txt</h2>
            <div>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings' ); ?>
                <label>
                    <input
                            type="checkbox"
                            name="organic_enable_ads_txt_redirect"
                            id="organic_enable_ads_txt_redirect"
                        <?php echo $ads_txt_redirect ? 'checked' : ''; ?>
                    />
                    Redirect to API-centric <strong>ads.txt URL</strong>
                </label>
                <input type="hidden" name="organic_ads_txt_redirect" id="organic_ads_txt_redirect" value="true" />
                <p><input type="submit" value="Save"/></p>
            </form>
            </div>
            <div id="cont_organic_sync_ads_txt"  style="display: <?php echo ( $ads_txt_redirect ? 'none' : 'block' ); ?>;">
            <form method="post">
                <?php wp_nonce_field( 'organic_settings' ); ?>
                <label>ads.txt
                    <textarea
                            name="organic_ads_txt"
                            id="organic_ads_txt"
                            style="width:650px; height: 500px; display: block;"
                            readonly
                    ><?php echo esc_textarea( $ads_txt ); ?></textarea>
                </label>
                <input type="hidden" name="organic_sync_ads_txt" id="organic_sync_ads_txt" value="true" />
                <p><input type="submit" value="Sync ads.txt"/></p>
            </form>
            </div>
            <hr />
            <p>Known Posts: <?php echo number_format( $total_published_posts ); ?></p>
            <p>Recently Updated Posts (unsynced): <?php echo number_format( $total_synced_posts ); ?></p>

            <hr />
            <h2>Post Types</h2>
            <p>Which post types from your CMS should be treated as Content for synchronization with
                the Organic Platform and eligible for Ads to be injected?</p>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings' ); ?>
                <ul>
                    <?php
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
                            <input type="checkbox" <?php echo esc_attr( $checked ); ?>
                                   name="organic_post_types[]"
                                   value="<?php echo esc_attr( $post_type ); ?>"
                            />
                            <?php echo esc_html( $post_type ); ?>
                            </label>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
                <p><input type="submit" value="Save" />
            </form>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings' ); ?>
                <input type="hidden" name="organic_content_sync" value="1" />
                <input type="submit" value="Sync Content Batch" />
            </form>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings' ); ?>
                <input type="hidden" name="organic_content_id_sync" value="1" />
                <input type="submit" value="Sync Content IDs" />
            </form>
            <form method="post">
                <?php wp_nonce_field( 'organic_settings' ); ?>
                <input type="hidden" name="organic_category_sync" value="1" />
                <input type="submit" value="Sync Categories" />
            </form>
        </div>
        <?php
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

    /**
     * Hook to see any newly created or updated posts and make sure we mark them as "dirty" for
     * future Organic sync
     *
     * @param $post_ID
     * @param $post
     * @param $update
     */
    public function handleSavePostHook( $post_ID, $post, $update ) {
        if ( ! $this->organic->isEnabled() ) {
            return;
        }

        if ( ! $this->organic->isPostEligibleForSync( $post ) ) {
            return;
        }

        update_post_meta( $post_ID, SYNC_META_KEY, 'unsynced' );

        if ( $this->organic->getContentForeground() ) {
            $this->organic->syncPost( $post );
        }
    }
}
