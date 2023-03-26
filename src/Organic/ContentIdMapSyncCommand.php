<?php

namespace Organic;

class ContentIdMapSyncCommand {


    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( 'organic-sync-content-id-map', $this );
        }

        // Include this command in cron schedule every hour
        add_action( 'organic_cron_sync_content_id_map', [ $this, 'run' ] );
        if ( ! wp_next_scheduled( 'organic_cron_sync_content_id_map' ) ) {
            wp_schedule_event( time(), 'hourly', 'organic_cron_sync_content_id_map' );
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run() {
         $this->__invoke( [] );
    }

    /**
     * Execute the command to synchronize content from the current site into Organic Platform
     *
     * @param array $args The attributes.
     * @return void
     */
    public function __invoke( $args ) {
        if ( ! $this->organic->isEnabledAndConfigured() ) {
            $this->organic->warning( 'Cannot sync Content Id Map without enabled integration with SDK API Key and Site ID' );
            return;
        }

        $stats = $this->organic->syncContentIdMap();
        $this->organic->info( 'Organic ContentIdMap Sync stats', $stats );
    }
}
