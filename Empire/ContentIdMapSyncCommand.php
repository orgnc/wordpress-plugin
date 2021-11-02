<?php

namespace Empire;

class ContentIdMapSyncCommand
{

    /**
     * @var Empire
     */
    private $empire;

    public function __construct(Empire $empire)
    {
        $this->empire = $empire;
        if (class_exists('\WP_CLI')) {
            // Expose this command to the WP-CLI command list
            \WP_CLI::add_command('empire-sync-content-id-map', $this);
        }

        // Include this command in cron schedule every hour
        add_action('empire_cron_sync_content_id_map', array( $this, 'run' ));
        if (! wp_next_scheduled('empire_cron_sync_content_id_map')) {
            wp_schedule_event(time(), 'hourly', 'empire_cron_sync_content_id_map');
        }
    }

    /**
     * Wrapper for __invoke with no args to make it cron friendly
     */
    public function run()
    {
        $this->__invoke(array());
    }

    /**
     * Execute the command to synchronize content from the current site into Empire
     *
     * @param array $args The attributes.
     * @return void
     */
    public function __invoke($args)
    {
        // Only both trying if the API key is set
        if (! $this->empire->getSdkKey() || ! $this->empire->getSiteId()) {
            $this->empire->log('Cannot sync Content Id Map without Empire SDK API Key and Site ID');
            return;
        }

        $stats = $this->empire->syncContentIdMap();
        $this->empire->log('Empire ContentIdMap Sync: ' . json_encode($stats));
    }
}
