<?php


namespace Organic;

class PluginConfigSyncCommand extends SimpleConfigSyncCommand {

    /**
     * Execute the command to pull latest plugin config for the current site
     */
    protected function invoke() {
        $stats = $this->organic->syncPluginConfig();
        $this->organic->info( 'Organic PluginConfig Sync stats', $stats );
    }

}
