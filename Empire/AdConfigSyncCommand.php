<?php


namespace Empire;

class AdConfigSyncCommand {

    /**
     * @var Empire
     */
    private $empire;

    public function __construct( Empire $empire ) {
        $this->empire = $empire;
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( 'empire-sync-ad-config', $this );
        }

        add_filter(
            'cron_schedules',
            function ( $schedules ) {
                $schedules['empire_every10minutes'] = array(
                    'interval' => 600,
                    'display' => __( 'Every 10 minutes' ),
                );
                return $schedules;
            }
        );

        // Include this command in cron schedule every hour
        add_action( 'empire_cron_sync_ad_config', array( $this, 'run' ) );
        if ( ! wp_next_scheduled( 'empire_cron_sync_ad_config' ) ) {
            wp_schedule_event( time(), 'empire_every10minutes', 'empire_cron_sync_ad_config' );
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run() {
        $this->__invoke( array() );
    }

    /**
     * Execute the command to synchronize content from the current site into Empire
     *
     * @param array $args The attributes.
     * @return void
     */
    public function __invoke( $args ) {
        // Only both trying if the API key is set
        if ( ! $this->empire->getSdkKey() || ! $this->empire->getSiteId() ) {
            $this->empire->log( 'Cannot sync AdConfig without Empire SDK API Key and Site ID' );
            return;
        }

        $stats = $this->empire->syncAdConfig();
        $this->empire->log( 'Empire AdConfig Sync: ' . json_encode( $stats ) );
    }
}
