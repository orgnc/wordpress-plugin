<?php

namespace Organic;

class ConnatixConfig extends BaseConfig {
    const CONNATIX_DEFAULTS = [
        'enabled' => false,
        'playspaceId' => '',
    ];

    /**
     * @var Organic
     */
    private $organic;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var string
     */
    private $playspaceId;

    public function __construct( Organic $organic ) {

        $rawAdsConfig = $organic->getOption( 'organic::ad_settings', [] );
        $raw = $rawAdsConfig['connatix'] ?: [];

        $config = array_merge( self::CONNATIX_DEFAULTS, $raw );
        parent::__construct( $config );

        $this->enabled = $config['enabled'] ?: $organic->getOption( 'organic::connatix_enabled' );
        $this->playspaceId = $config['playspaceId'] ?: $organic->getOption( 'organic::connatix_playspace_id' );
        $this->organic = $organic;
    }

    public function isEnabled() {
        if (
            $this->organic->isEnabled() && $this->enabled && $this->playspaceId
        ) {
            return true;
        }
        return false;
    }

    public function getPlayspaceId() : string {
        if ( $this->isEnabled() ) {
            return $this->playspaceId;
        }
        return '';
    }
}
