<?php


namespace Organic;

class AffiliateConfigSyncCommand extends SimpleConfigSyncCommand {

    /**
     * Execute the command to pull latest Affiliate config for the current site
     */
    protected function invoke() {
        $stats = $this->organic->syncAffiliateConfig();
        $this->organic->info( 'Organic AffiliateConfig Sync stats', $stats );
    }

}
