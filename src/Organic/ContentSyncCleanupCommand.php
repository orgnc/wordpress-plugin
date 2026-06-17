<?php

namespace Organic;

class ContentSyncCleanupCommand {

    /**
     * @var Organic
     */
    private $organic;

    public function __construct( Organic $organic ) {
        $this->organic = $organic;
        if ( class_exists( '\WP_CLI' ) ) {
            \WP_CLI::add_command( 'organic-cleanup-content-sync', $this );
        }
    }

    /**
     * Clean up data left by the removed content sync feature.
     *
     * ## OPTIONS
     *
     * [--postmeta]
     * : Also delete old empire_sync postmeta rows.
     *
     * [--batch-size=<count>]
     * : Number of postmeta rows to delete per batch when --postmeta is set.
     * ---
     * default: 1000
     * ---
     *
     * @param array $args Positional arguments.
     * @param array $opts Associative arguments.
     * @return void
     */
    public function __invoke( $args, $opts ) {
        $this->clearCronHooks();
        $this->deleteOptions();

        $deletedPostmeta = 0;
        if ( $opts['postmeta'] ?? false ) {
            $deletedPostmeta = $this->deletePostmeta( (int) ( $opts['batch-size'] ?? 1000 ) );
        }

        $this->organic->info(
            'Organic content sync cleanup completed',
            [
                'deleted_postmeta' => $deletedPostmeta,
                'postmeta_cleanup_enabled' => (bool) ( $opts['postmeta'] ?? false ),
            ]
        );
    }

    private function clearCronHooks() {
        wp_clear_scheduled_hook( 'organic_cron_sync_content' );
        wp_clear_scheduled_hook( 'organic_cron_sync_content_id_map' );
    }

    private function deleteOptions() {
        foreach (
            [
                'organic::content_foreground',
                'empire::content_foreground',
                'organic::resynced_on_version',
                'empire::resynced_on_version',
                'organic::content_resync_started_at',
                'empire::content_resync_started_at',
                'organic::content_sync_removed_cleanup_complete',
            ] as $option
        ) {
            delete_option( $option );
        }
    }

    private function deletePostmeta( int $batchSize ): int {
        global $wpdb;

        $batchSize = max( 1, $batchSize );
        $totalDeleted = 0;

        do {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d",
                    'empire_sync',
                    $batchSize
                )
            );

            $deleted = (int) $deleted;
            $totalDeleted += $deleted;
        } while ( $deleted === $batchSize );

        return $totalDeleted;
    }
}
