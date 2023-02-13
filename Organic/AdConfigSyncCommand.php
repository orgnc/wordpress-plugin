<?php


namespace Organic;

class AdConfigSyncCommand {

    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( 'organic-sync-ad-config', $this );
        }

        add_filter(
            'cron_schedules',
            function ( $schedules ) {
                $schedules['organic_every10minutes'] = [
                    'interval' => 600,
                    'display' => __( 'Every 10 minutes' ),
                ];
                return $schedules;
            }
        );

        // Include this command in cron schedule every hour
        add_action( 'organic_cron_sync_ad_config', [ $this, 'run' ] );
        if ( ! wp_next_scheduled( 'organic_cron_sync_ad_config' ) ) {
            wp_schedule_event( time(), 'organic_every10minutes', 'organic_cron_sync_ad_config' );
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run() {
        $this->__invoke( [] );
    }

    /**
     * Execute the command to pull latest AdConfig for the current site
     *
     * @param array $args The attributes.
     * @return void
     */
    public function __invoke( $args ) {
        // Only both trying if the API key is set
        if ( ! $this->organic->isEnabledAndConfigured() ) {
            $this->organic->warning( 'Cannot sync AdConfig without Organic SDK API Key and Site ID' );
            return;
        }

        $stats = $this->organic->syncAdConfig();
        $this->organic->info( 'Organic AdConfig Sync stats', $stats );
    }
}
