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

        add_filter( 'plugin_action_links_organic/organic.php', array( $this, 'pluginSettingsLink' ) );
        add_action( 'admin_menu', array( $this, 'adminMenu' ) );
        add_action( 'save_post', array( $this, 'handleSavePostHook' ), 10, 3 );
    }

    public function adminMenu() {
        add_submenu_page(
            'options-general.php',          // Slug of parent.
            'Organic Settings',             // Page Title.
            'Organic Settings ',            // Menu title.
            'manage_options',               // Capability.
            __FILE__,                       // Slug.
            array( $this, 'adminSettings' ) // Function to call.
        );
    }

    public function adminSettings() {
        // Save any setting updates
        if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
            if ( isset( $_POST['organic_sync_ads_txt'] ) ) {
                $this->organic->syncAdsTxt();
            } else if ( isset( $_POST['organic_post_types'] ) ) {
                $this->organic->updateOption( 'organic::post_types', $_POST['organic_post_types'], false );
                $this->organic->setPostTypes( $_POST['organic_post_types'] );
            } else if ( isset( $_POST['organic_content_sync'] ) ) {
                try {
                    $this->organic->syncContent( 100 );
                } catch ( \Exception $e ) {
                    echo 'Error: ' . $e->getMessage();
                    echo $e->getTraceAsString();
                }
            } else {
                $this->organic->updateOption( 'organic::enabled', isset( $_POST['organic_enabled'] ) ? true : false, false );
                $this->organic->updateOption( 'organic::percent_test', $_POST['organic_percent'], false );
                $this->organic->updateOption( 'organic::test_value', $_POST['organic_value'], false );
                $this->organic->updateOption( 'organic::connatix_enabled', isset( $_POST['organic_connatix_enabled'] ) ? true : false, false );
                $this->organic->updateOption( 'organic::connatix_playspace_id', $_POST['organic_connatix_playspace_id'], false );
                $this->organic->updateOption( 'organic::cmp', $_POST['organic_cmp'] ?: '', false );
                $this->organic->updateOption( 'organic::one_trust_id', $_POST['organic_one_trust_id'] ?: '', false );
                $this->organic->updateOption( 'organic::sdk_key', $_POST['organic_sdk_key'] ?: '', false );
                $this->organic->updateOption( 'organic::site_id', $_POST['organic_site_id'] ?: '', false );
                $this->organic->updateOption( 'organic::public_domain', $_POST['organic_public_domain'] ?: '', false );
                $this->organic->updateOption( 'organic::organic_domain', $_POST['organic_organic_domain'] ?: '', false );
                $this->organic->updateOption( 'organic::amp_ads_enabled', isset( $_POST['organic_amp_ads_enabled'] ) ? true : false, false );
                $this->organic->updateOption( 'organic::inject_ads_config', isset( $_POST['organic_inject_ads_config'] ) ? true : false, false );
                $this->organic->updateOption( 'organic::ad_slots_prefill_enabled', isset( $_POST['organic_ad_slots_prefill_enabled'] ) ? true : false, false );
                $this->organic->updateOption( 'organic::campaigns_enabled', isset( $_POST['organic_campaigns_enabled'] ) ? true : false, false );
                $this->organic->updateOption( 'organic::affiliate_enabled', isset( $_POST['organic_affiliate_enabled'] ) ? true : false, false );

                $this->organic->sdk->updateToken( $_POST['organic_sdk_key'] );
                $this->update_results[] = 'updated';
            }
        }

        $this->syncSettings();
        $this->showSettings();
        $this->showNotices();
    }

    protected function syncSettings() {
        if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
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
        $connatix_enabled = $this->organic->getOption( 'organic::connatix_enabled' );
        $connatix_playspace_id = $this->organic->getOption( 'organic::connatix_playspace_id' );
        $cmp = $this->organic->getOption( 'organic::cmp' );
        $one_trust_id = $this->organic->getOption( 'organic::one_trust_id' );
        $sdk_key = $this->organic->getOption( 'organic::sdk_key' );
        $site_id = $this->organic->getOption( 'organic::site_id' );
        $ads_txt = $this->organic->getOption( 'organic::ads_txt' );
        $organic_test = $this->organic->getOption( 'organic::percent_test' );
        $organic_value = $this->organic->getOption( 'organic::test_value' );
        $amp_ads_enabled = $this->organic->getOption( 'organic::amp_ads_enabled' );
        $inject_ads_config = $this->organic->getOption( 'organic::inject_ads_config' );
        $ad_slots_prefill_enabled = $this->organic->getOption( 'organic::ad_slots_prefill_enabled' );
        $campaigns_enabled = $this->organic->getOption( 'organic::campaigns_enabled' );
        $affiliate_enabled = $this->organic->getOption( 'organic::affiliate_enabled' );
        $site_public_domain = $this->organic->getOption( 'organic::public_domain' );
        $site_organic_domain = $this->organic->getOption( 'organic::organic_domain' );

        $total_published_posts = $this->organic->buildQuerySyncablePosts( 1 )->found_posts;
        $total_synced_posts = $this->organic->buildQueryNewlyUnsyncedPosts( 1 )->found_posts;
        if ( $this->update_results ) {
            $update_status = '<span class="update_success">' . join( ', ', $this->update_results ) . ' successfully </span>';
        } else {
            $update_status = '';
        }
        ?>
        <style>
            #organic_host {
                width: 400px;
            }
            .update_success {
                color: darkgreen;
            }
        </style>
        <div class="wrap">
            <h2>Organic Settings</h2>
            <form method="post">
                <p><label><input type="checkbox" name="organic_enabled"
                                 id="organic_enabled" <?php echo $enabled ? 'checked' : ''; ?>> Organic Integration
                        Enabled</label></p>

                <hr />
                <h3>Organic Settings</h3>
                <p><label>Organic API Key: <input type="text" name="organic_sdk_key" style="width: 355px;" value="<?php echo $sdk_key; ?>" /></label></p>
                <p><label>Organic Site ID: <input type="text" name="organic_site_id" style="width: 355px;" value="<?php echo $site_id; ?>" /></label></p>
                <p>
                    <label>Site Public Domain:
                        <input
                            type="text"
                            name="organic_public_domain"
                            style="width: 355px;"
                            id="organic_public_domain"
                            placeholder="organic.example.com"
                            value="<?php echo $site_public_domain; ?>"
                        />
                    </label>
                </p>
                <p>
                    <label> Site Organic Domain:
                        <input
                            type="text"
                            name="organic_organic_domain"
                            style="width: 355px;"
                            id="organic_organic_domain"
                            placeholder="example-com.organicly.io"
                            value="<?php echo $site_organic_domain; ?>"
                        />
                    </label>
                </p>

                <p><label>Consent Management:
                        <select id="organic_cmp" name="organic_cmp">
                            <option value="">None (WARNING: DO NOT USE IN PRODUCTION)</option>
                            <option value="built-in" <?php echo ( $cmp == 'built-in' ? 'selected="selected"' : '' ); ?>>Built In</option>
                            <option value="one-trust" <?php echo ( $cmp == 'one-trust' ? 'selected="selected"' : '' ); ?>>One Trust</option>
                        </select>
                    </label></p>
                <p id="one-trust-config" style="display: <?php echo ( $cmp == 'one-trust' ? 'block' : 'none' ); ?>;">
                    <label>One Trust ID: <input type="text" name="organic_one_trust_id" style="width: 355px;" value="<?php echo $one_trust_id; ?>" /></label>
                </p>
                <script>
                    var hideShowOneTrust = function() {
                        if ( document.getElementById("organic_cmp").value === 'one-trust' ) {
                            document.getElementById('one-trust-config').style.display = "block";
                        } else {
                            document.getElementById('one-trust-config').style.display = "none";
                        }
                    }
                    document.getElementById("organic_cmp").onchange = hideShowOneTrust;
                    document.getElementById("organic_cmp").onclick = hideShowOneTrust;
                    document.getElementById("organic_cmp").onkeypress = hideShowOneTrust;
                </script>
                <p><label><input type="checkbox" name="organic_connatix_enabled"
                                 id="organic_connatix_enabled" <?php echo $connatix_enabled ? 'checked' : ''; ?>> Connatix Ads
                        Enabled</label></p>
                <p id="connatix-config" style="display: <?php echo ( $connatix_enabled ? 'block' : 'none' ); ?>;">
                    <label>Playspace Player ID: <input type="text" name="organic_connatix_playspace_id" style="width: 355px;" value="<?php echo $connatix_playspace_id; ?>" /></label>
                </p>
                <script>
                    var hideShowConnatix = function() {
                        if ( document.getElementById("organic_connatix_enabled").checked ) {
                            document.getElementById('connatix-config').style.display = "block";
                        } else {
                            document.getElementById('connatix-config').style.display = "none";
                        }
                    }
                    document.getElementById("organic_connatix_enabled").onchange = hideShowConnatix;
                    document.getElementById("organic_connatix_enabled").onclick = hideShowConnatix;
                    document.getElementById("organic_connatix_enabled").onkeypress = hideShowConnatix;
                </script>
                <p><label>% of ads on Organic Ads: <input type="text" name="organic_percent" id="organic_percent" value="<?php echo $organic_test; ?>" /></label></p>
                <p><label>Key-Value for Split Test: <input type="text" name="organic_value" id="organic_value" value="<?php echo $organic_value; ?>" /></label></p>

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
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="organic_affiliate_enabled"
                                id="organic_affiliate_enabled"
                        <?php echo $affiliate_enabled ? 'checked' : ''; ?>
                        />
                        Affiliate Application is enabled on the Platform
                    </label>
                </p>
                <p>
                    <input id="update-submit" type="submit" name="organic_update" value="Update" />
                    &nbsp;
                    <input id="update-and-sync-submit" type="submit" name="organic_update" value="Update and sync" />
                <?php echo $update_status; ?>
                </p>
            </form>
            <h2>Ads.txt</h2>
            <form method="post">
                <label>ads.txt
                    <textarea
                            name="organic_ads_txt"
                            id="organic_ads_txt"
                            style="width:650px; height: 500px; display: block;"
                            readonly
                    ><?php echo $ads_txt; ?></textarea>
                </label>
                <input type="hidden" name="organic_sync_ads_txt" id="organic_sync_ads_txt" value="true" />
                <p><input type="submit" value="Sync ads.txt"/></p>
            </form>

            <hr />
            <p>Known Posts: <?php echo number_format( $total_published_posts ); ?></p>
            <p>Recently Updated Posts (unsynced): <?php echo number_format( $total_synced_posts ); ?></p>

            <hr />
            <h2>Post Types</h2>
            <p>Which post types from your CMS should be treated as Content for synchronization with
                the Organic Platform and eligible for Ads to be injected?</p>
            <form method="post">
                <ul>
                <?php
                $post_types = get_post_types(
                    array(
                        'public'   => true,
                        '_builtin' => false,
                    )
                );
                    $post_types[] = 'post';
                    $post_types[] = 'page';
                foreach ( $post_types as $post_type ) {
                    $checked = '';
                    if ( in_array( $post_type, $this->organic->getPostTypes() ) ) {
                        $checked = 'checked="checked"';
                    }

                    echo '<li><label>';
                    echo "<input type='checkbox' $checked name='organic_post_types[]' value='" . $post_type . "' /> ";
                    echo $post_type;
                    echo "</label></li>\n";
                }
                ?>
                </ul>
                <p><input type="submit" value="Save" />
            </form>
            <form method="post">
                <input type="hidden" name="organic_content_sync" value="1" />
                <input type="submit" value="Sync Content Batch" />
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
    }
}
