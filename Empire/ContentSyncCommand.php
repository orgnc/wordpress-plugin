<?php


namespace Empire;

class ContentSyncCommand {

    /**
     * @var Empire
     */
    private $empire;

    public function __construct( Empire $empire ) {
        $this->empire = $empire;
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( 'empire_sync_content', $this );
        }

        // Include this command in cron schedule every minute
        add_action( 'empire_cron_sync_content', array( $this, 'run' ) );
        if ( ! wp_next_scheduled( 'empire_cron_sync_content' ) ) {
            wp_schedule_event( time(), 'hourly', 'empire_cron_sync_content' );
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
        // Only both trying if the API key is set
        if ( ! $this->empire->getSdkKey() || ! $this->empire->getSiteId() ) {
            $this->empire->log( 'Cannot sync articles without Empire SDK API Key and Site ID' );
            return;
        }

        if ( $opts['full'] ?? false ) {
            $this->empire->debug( 'Empire Sync: opts=' . json_encode($opts) );
            $updated = $this->empire->fullResyncContent(
                (int)($opts['batch-size'] ?? 100),
                (int)($opts['start-from'] ?? 0),
                (int)($opts['sleep-between'] ?? 1),
            );
            $this->empire->log( 'Empire Sync: total_posts=' . $updated );
            return;
        }

        $post_ids = array_filter(explode(',', ($opts['posts'] ?? '')));
        if ( count( $post_ids ) ) {
            foreach ( $post_ids as $post_id ) {
                $post = \WP_Post::get_instance( $post_id );
                if ( ! $post ) {
                    $this->empire->log( 'Post ' . $post_id . ' not found. Skipping...' );
                    continue;
                }
                $this->empire->syncPost( $post );
            }
            return;
        }

        $updated = $this->empire->syncContent();
        $this->empire->log( 'Empire Sync: total_posts=' . $updated );
    }
}
