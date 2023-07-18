<?php

namespace Organic;

class ContentSyncCommand {


    /**
     * @var Organic
     */
    protected $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( 'organic-sync-content', $this );
        }

        add_filter(
            'cron_schedules',
            function ( $schedules ) {
                $schedules['organic_content_sync'] = [
                    'interval' => 600,
                    'display' => __( 'Organic Content Sync' ),
                ];
                return $schedules;
            }
        );

        // Include this command in cron schedule every minute
        add_action( 'organic_cron_sync_content', [ $this, 'run' ] );
        if ( ! wp_next_scheduled( 'organic_cron_sync_content' ) ) {
            wp_schedule_event( time(), 'organic_content_sync', 'organic_cron_sync_content' );
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run() {
        $this->organic->info( 'organic_cron_sync_content starting...' );
        $this->__invoke( [], [] );
        $this->organic->info( 'organic_cron_sync_content completed' );
    }

    /**
     * Execute the command to synchronize content from the current site into Organic
     *
     * [--full]
     * : Enforce full re-sync
     *
     * [--batch-size]
     * : Size of the batch
     *
     * [--start-from]
     * : Start sync from offset
     *
     * [--sleep-between]
     * : Sleep for N seconds between batches
     *
     * [--posts]
     * : List of comma-separated post IDs: --posts=1234,34534,6456
     *
     * @param array $args The attributes
     * @param array $opts The options
     * @return void
     * @throws Exception if post published or modified date is invalid
     * @since 0.1.0
     */
    public function __invoke( $args, $opts ) {
        $this->organic->info(
            __CLASS__ . ' invoked',
            [
                'args' => $args,
                'opts' => $opts,
            ]
        );
        if ( ! $this->organic->isEnabledAndConfigured() ) {
            $this->organic->warning( 'Cannot sync articles without enabled integration with SDK API Key and Site ID' );
            return;
        }

        if ( $opts['full'] ?? false ) {
            $updated = $this->organic->fullResyncContent(
                (int) ( $opts['batch-size'] ?? 100 ),
                (int) ( $opts['start-from'] ?? 0 ),
                (int) ( $opts['sleep-between'] ?? 1 )
            );
            $this->organic->info( 'Organic Sync stats', [ 'updated' => $updated ] );
            return;
        }

        $this->organic->info( 'Organic Sync ... categories' );
        $this->organic->syncCategories();

        $post_ids = array_filter( explode( ',', ( $opts['posts'] ?? '' ) ) );
        if ( count( $post_ids ) ) {
            $this->organic->info( 'Organic Sync ... specific posts' );
            foreach ( $post_ids as $post_id ) {
                $post = \WP_Post::get_instance( $post_id );
                if ( ! $post ) {
                    $this->organic->info( 'Post ' . $post_id . ' not found. Skipping...' );
                    continue;
                }
                $this->organic->syncPost( $post );
            }
            return;
        }

        $updated = $this->organic->syncContent( 200 );
        $this->organic->info( 'Organic Sync stats', [ 'updated' => $updated ] );
    }
}
