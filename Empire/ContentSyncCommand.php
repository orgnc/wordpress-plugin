<?php

namespace Empire;

class ContentSyncCommand
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
            \WP_CLI::add_command('empire_sync_content', $this);
        }

        // Include this command in cron schedule every minute
        add_action('empire_cron_sync_content', array( $this, 'run' ));
        if (! wp_next_scheduled('empire_cron_sync_content')) {
            wp_schedule_event(time(), 'hourly', 'empire_cron_sync_content');
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
     * @throws Exception if post published or modified date is invalid
     * @since 0.1.0
     */
    public function __invoke($args)
    {
        // Only both trying if the API key is set
        if (! $this->empire->getSdkKey() || ! $this->empire->getSiteId()) {
            $this->empire->log('Cannot sync articles without Empire SDK API Key and Site ID');
        } else {
            if (! count($args)) {
                $updated = $this->empire->syncContent();
                $this->empire->log('Empire Sync: total_posts=' . $updated);
                return;
            }

            foreach ($args as $post_id) {
                $post = \WP_Post::get_instance($post_id);
                if (! $post) {
                    $this->empire->log('Post ' . $post_id . ' not found. Skipping...');
                    continue;
                }
                $this->empire->syncPost($post);
            }
        }
    }
}
