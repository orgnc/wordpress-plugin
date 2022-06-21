<?php


namespace Organic;

class AffiliateConfigSyncCommand {

    // logic borrowed from AdConfigSyncCommand

    const SYNC_COMMAND = 'organic-sync-affiliate-config';
    const CRON_SYNC_COMMAND = 'organic_cron_sync_affiliate_config';

    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( self::SYNC_COMMAND, $this );
        }

        add_filter(
            'cron_schedules',
            function ( $schedules ) {
                $schedules['organic_every10minutes'] = array(
                    'interval' => 600,
                    'display' => __( 'Every 10 minutes' ),
                );
                return $schedules;
            }
        );

        // Include this command in cron schedule every hour
        add_action( self::CRON_SYNC_COMMAND, array( $this, 'run' ) );
        if ( ! wp_next_scheduled( self::CRON_SYNC_COMMAND ) ) {
            wp_schedule_event( time(), 'organic_every10minutes', self::CRON_SYNC_COMMAND );
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run() {
        $this->__invoke( array() );
    }

    /**
     * Execute the command to pull latest Affiliate config for the current site
     *
     * @param array $args The attributes.
     * @return void
     */
    public function __invoke( $args ) {
        // Only both trying if the API key is set
        if ( ! $this->organic->getSdkKey() || ! $this->organic->getSiteId() ) {
            $this->organic->warning( 'Cannot sync AffiliateConfig without Organic SDK API Key and Site ID' );
            return;
        }

        $stats = $this->organic->syncAffiliateConfig();
        $this->organic->info( 'Organic AffiliateConfig Sync stats', $stats );
    }
}
