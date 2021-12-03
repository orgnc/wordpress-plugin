<?php

namespace Empire;

class AdminSettings {

    /**
     * @var Empire
     */
    private $empire;

    public function __construct( Empire $empire ) {
        $this->empire = $empire;

        add_filter( 'plugin_action_links_empire/empire.php', array( $this, 'pluginSettingsLink' ) );
        add_action( 'admin_menu', array( $this, 'adminMenu' ) );
        add_action( 'save_post', array( $this, 'handleSavePostHook' ), 10, 3 );
    }

    public function adminMenu() {
        add_submenu_page(
            'options-general.php',          // Slug of parent.
            'Empire Settings',              // Page Title.
            'Empire Settings ',             // Menu title.
            'manage_options',               // Capability.
            __FILE__,                       // Slug.
            array( $this, 'adminSettings' ) // Function to call.
        );
    }

    public function adminSettings() {
        // Save any setting updates
        if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
            if ( isset( $_POST['empire_sync_ads_txt'] ) ) {
                $this->empire->syncAdsTxt();
            } else if ( isset( $_POST['empire_post_types'] ) ) {
                update_option( 'empire::post_types', $_POST['empire_post_types'], false );
                $this->empire->setPostTypes( $_POST['empire_post_types'] );
            } else {
                update_option( 'empire::enabled', isset( $_POST['empire_enabled'] ) ? true : false, false );
                update_option( 'empire::percent_test', $_POST['empire_percent'], false );
                update_option( 'empire::test_value', $_POST['empire_value'], false );
                update_option( 'empire::connatix_enabled', isset( $_POST['empire_connatix_enabled'] ) ? true : false, false );
                update_option( 'empire::connatix_playspace_id', $_POST['empire_connatix_playspace_id'], false );
                update_option( 'empire::cmp', $_POST['empire_cmp'] ?: '', false );
                update_option( 'empire::one_trust_id', $_POST['empire_one_trust_id'] ?: '', false );
                update_option( 'empire::sdk_key', $_POST['empire_sdk_key'] ?: '', false );
                update_option( 'empire::site_id', $_POST['empire_site_id'] ?: '', false );
                update_option( 'empire::amp_ads_enabled', isset( $_POST['empire_amp_ads_enabled'] ) ? true : false, false );
                update_option( 'empire::inject_ads_config', isset( $_POST['empire_inject_ads_config'] ) ? true : false, false );
                update_option( 'empire::ad_slots_prefill_enabled', isset( $_POST['empire_ad_slots_prefill_enabled'] ) ? true : false, false );
                update_option( 'empire::campaigns_enabled', isset( $_POST['empire_campaigns_enabled'] ) ? true : false, false );
                $this->empire->sdk->updateToken( $_POST['empire_sdk_key'] );

                echo '<h3>Updates Saved</h3>';
            }
        }

        $this->showSettings();
    }

    public function showSettings() {
        $enabled = get_option( 'empire::enabled' );
        $connatix_enabled = get_option( 'empire::connatix_enabled' );
        $connatix_playspace_id = get_option( 'empire::connatix_playspace_id' );
        $cmp = get_option( 'empire::cmp' );
        $one_trust_id = get_option( 'empire::one_trust_id' );
        $sdk_key = get_option( 'empire::sdk_key' );
        $site_id = get_option( 'empire::site_id' );
        $ads_txt = get_option( 'empire::ads_txt' );
        $empire_test = get_option( 'empire::percent_test' );
        $empire_value = get_option( 'empire::test_value' );
        $amp_ads_enabled = get_option( 'empire::amp_ads_enabled' );
        $inject_ads_config = get_option( 'empire::inject_ads_config' );
        $ad_slots_prefill_enabled = get_option( 'empire::ad_slots_prefill_enabled' );
        $campaigns_enabled = get_option( 'empire::campaigns_enabled' );

        $total_published_posts = $this->empire->buildQuerySyncablePosts( 1 )->found_posts;
        $total_synced_posts = $this->empire->buildQueryNewlyUnsyncedPosts( 1 )->found_posts;
        ?>
        <style>
            #empire_host {
                width: 400px;
            }
        </style>
        <div class="wrap">
            <h2>Empire Settings</h2>
            <form method="post">
                <p><label><input type="checkbox" name="empire_enabled"
                                 id="empire_enabled" <?php echo $enabled ? 'checked' : ''; ?>> Empire Integration
                        Enabled</label></p>

                <hr />
                <h3>Empire Settings</h3>
                <p><label>Empire API Key: <input type="text" name="empire_sdk_key" style="width: 355px;" value="<?php echo $sdk_key; ?>" /></label></p>
                <p><label>Empire Site ID: <input type="text" name="empire_site_id" style="width: 355px;" value="<?php echo $site_id; ?>" /></label></p>

                <p><label>Consent Management:
                        <select id="empire_cmp" name="empire_cmp">
                            <option value="">None (WARNING: DO NOT USE IN PRODUCTION)</option>
                            <option value="built-in" <?php echo ( $cmp == 'built-in' ? 'selected="selected"' : '' ); ?>>Built In</option>
                            <option value="one-trust" <?php echo ( $cmp == 'one-trust' ? 'selected="selected"' : '' ); ?>>One Trust</option>
                        </select>
                    </label></p>
                <p id="one-trust-config" style="display: <?php echo ( $cmp == 'one-trust' ? 'block' : 'none' ); ?>;">
                    <label>One Trust ID: <input type="text" name="empire_one_trust_id" style="width: 355px;" value="<?php echo $one_trust_id; ?>" /></label>
                </p>
                <script>
                    var hideShowOneTrust = function() {
                        if ( document.getElementById("empire_cmp").value === 'one-trust' ) {
                            document.getElementById('one-trust-config').style.display = "block";
                        } else {
                            document.getElementById('one-trust-config').style.display = "none";
                        }
                    }
                    document.getElementById("empire_cmp").onchange = hideShowOneTrust;
                    document.getElementById("empire_cmp").onclick = hideShowOneTrust;
                    document.getElementById("empire_cmp").onkeypress = hideShowOneTrust;
                </script>
                <p><label><input type="checkbox" name="empire_connatix_enabled"
                                 id="empire_connatix_enabled" <?php echo $connatix_enabled ? 'checked' : ''; ?>> Connatix Ads
                        Enabled</label></p>
                <p id="connatix-config" style="display: <?php echo ( $connatix_enabled ? 'block' : 'none' ); ?>;">
                    <label>Playspace Player ID: <input type="text" name="empire_connatix_playspace_id" style="width: 355px;" value="<?php echo $connatix_playspace_id; ?>" /></label>
                </p>
                <script>
                    var hideShowConnatix = function() {
                        if ( document.getElementById("empire_connatix_enabled").checked ) {
                            document.getElementById('connatix-config').style.display = "block";
                        } else {
                            document.getElementById('connatix-config').style.display = "none";
                        }
                    }
                    document.getElementById("empire_connatix_enabled").onchange = hideShowConnatix;
                    document.getElementById("empire_connatix_enabled").onclick = hideShowConnatix;
                    document.getElementById("empire_connatix_enabled").onkeypress = hideShowConnatix;
                </script>
                <p><label>% of ads on Empire: <input type="text" name="empire_percent" id="empire_percent" value="<?php echo $empire_test; ?>" /></label></p>
                <p><label>Key-Value for Split Test: <input type="text" name="empire_value" id="empire_value" value="<?php echo $empire_value; ?>" /></label></p>

                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="empire_amp_ads_enabled"
                                id="empire_amp_ads_enabled"
                            <?php echo $amp_ads_enabled ? 'checked' : ''; ?>
                        />
                        AMP Ads Enabled
                    </label>
                </p>
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="empire_inject_ads_config"
                                id="empire_inject_ads_config"
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
                                name="empire_ad_slots_prefill_enabled"
                                id="empire_ad_slots_prefill_enabled"
                            <?php echo $ad_slots_prefill_enabled ? 'checked' : ''; ?>
                        />
                        Prefill ad containers to prevent Content Layout Shift (CLS) issues
                    </label>
                </p>
                <p>
                    <label>
                        <input
                                type="checkbox"
                                name="empire_campaigns_enabled"
                                id="empire_campaigns_enabled"
                            <?php echo $campaigns_enabled ? 'checked' : ''; ?>
                        />
                        Campaigns Application is enabled on the Platform
                    </label>
                </p>
                <p><input type="submit" value="Update"/></p>
            </form>
            <h2>Ads.txt</h2>
            <form method="post">
                <label>ads.txt
                    <textarea
                            name="empire_ads_txt"
                            id="empire_ads_txt"
                            style="width:650px; height: 500px; display: block;"
                            readonly
                    ><?php echo $ads_txt; ?></textarea>
                </label>
                <input type="hidden" name="empire_sync_ads_txt" id="empire_sync_ads_txt" value="true" />
                <p><input type="submit" value="Sync ads.txt"/></p>
            </form>

            <hr />
            <p>Known Posts: <?php echo number_format( $total_published_posts ); ?></p>
            <p>Recently Updated Posts (unsynced): <?php echo number_format( $total_synced_posts ); ?></p>

            <hr />
            <h2>Post Types</h2>
            <p>Which post types from your CMS should be treated as Content for synchronization with
                the Empire platform and eligible for Ads to be injected?</p>
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
                        if ( in_array( $post_type, $this->empire->getPostTypes() ) ) {
                            $checked = 'checked="checked"';
                        }

                        echo '<li><label>';
                        echo "<input type='checkbox' $checked name='empire_post_types[]' value='" . $post_type . "' /> ";
                        echo $post_type;
                        echo "</label></li>\n";
                    }
                    ?>
                </ul>
                <p><input type="submit" value="Save" />
            </form>
        </div>
        <?php
    }

    public function pluginSettingsLink( $links ) {
        // Build and escape the URL.
        $url = esc_url(
            add_query_arg(
                'page',
                'empire/Empire/AdminSettings.php',
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
     * future Empire sync
     *
     * @param $post_ID
     * @param $post
     * @param $update
     */
    public function handleSavePostHook( $post_ID, $post, $update ) {
        if ( ! $this->empire->isPostEligibleForSync( $post ) ) {
            return;
        }

        update_post_meta( $post_ID, SYNC_META_KEY, 'unsynced' );
        // Only run the sync if we are actually configured
        if ( $this->empire->getSiteId() && $this->empire->getSdkKey() ) {
            $this->empire->syncPost( $post );
        }
    }
}
