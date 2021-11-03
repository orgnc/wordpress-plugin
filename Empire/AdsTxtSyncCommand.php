<?php

namespace Empire;

class AdsTxtSyncCommand {


    /**
     * @var Empire
     */
    private $empire;

    public function __construct( Empire $empire ) {
        $this->empire = $empire;
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( 'empire-sync-ads-txt', $this );
        }

        // Include this command in cron schedule every hour
        add_action( 'empire_cron_sync_ads_txt', array( $this, 'run' ) );
        if ( ! wp_next_scheduled( 'empire_cron_sync_ads_txt' ) ) {
            wp_schedule_event( time(), 'hourly', 'empire_cron_sync_ads_txt' );
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run() {
         $this->__invoke( array() );
    }

    /**
     * Execute the command to synchronize ads txt from Empire
     *
     * @param array $args The attributes.
     * @return void
     */
    public function __invoke( $args ) {
        // Only both trying if the API key is set
        if ( ! $this->empire->getSdkKey() || ! $this->empire->getSiteId() ) {
            $this->empire->log( 'Cannot sync Ads.txt without Empire SDK API Key and Site ID' );
            return;
        }

        $stats = $this->empire->syncAdsTxt();
        $this->empire->log( 'Ads.txt Sync: ' . json_encode( $stats ) );
    }
}
