<?php

namespace Organic;

class AdsTxtSyncCommand {


    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        $adsTxtRedirect = $this->organic->adsTxtRedirectionEnabled();
        if ( $adsTxtRedirect ) {
            return;
        }
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( 'organic-sync-ads-txt', $this );
        }

        // Include this command in cron schedule every hour
        add_action( 'organic_cron_sync_ads_txt', [ $this, 'run' ] );
        if ( ! wp_next_scheduled( 'organic_cron_sync_ads_txt' ) ) {
            wp_schedule_event( time(), 'hourly', 'organic_cron_sync_ads_txt' );
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run() {
         $this->__invoke( [] );
    }

    /**
     * Execute the command to synchronize ads txt from Organic Ads
     *
     * @param array $args The attributes.
     * @return void
     */
    public function __invoke( $args ) {
        $adsTxtRedirect = $this->organic->adsTxtRedirectionEnabled();
        if ( $adsTxtRedirect ) {
            $this->organic->warning( 'Ads.txt Sync is disabled for this site' );
            return;
        }

        // Only both trying if the API key is set
        if ( ! $this->organic->isEnabledAndConfigured() ) {
            $this->organic->warning( 'Cannot sync Ads.txt without Organic SDK API Key and Site ID' );
            return;
        }

        $stats = $this->organic->syncAdsTxt();
        $this->organic->info( 'Ads.txt Sync stats', $stats );
    }
}
