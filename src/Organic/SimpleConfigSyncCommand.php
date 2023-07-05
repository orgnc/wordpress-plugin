<?php


namespace Organic;

abstract class SimpleConfigSyncCommand {

    /**
     * @var Organic
     */
    protected $organic;

    public function __construct( Organic $organic, string $syncCommand, string $cronSyncCommand ) {
        $this->organic = $organic;
        if ( class_exists( '\WP_CLI' ) ) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command( $syncCommand, $this );
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
        add_action( $cronSyncCommand, [ $this, 'run' ] );
        if ( ! wp_next_scheduled( $cronSyncCommand ) ) {
            wp_schedule_event( time(), 'organic_every10minutes', $cronSyncCommand );
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run() {
        $this->__invoke( [] );
    }

    /**
     * The command to run.
     */
    abstract protected function invoke();

    /**
     * Execute the command to pull latest config for the current site
     *
     * @param array $args The attributes.
     * @return void
     */
    public function __invoke( $args ) {
        // Only worth trying if the API key is set
        if ( ! $this->organic->isConfigured() ) {
            $this->organic->warning( 'Cannot sync data without Organic SDK API Key and Site ID' );
            return;
        }
        $this->invoke();
    }
}
