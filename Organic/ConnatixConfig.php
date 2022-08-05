<?php

namespace Organic;

class ConnatixConfig extends BaseConfig {
    const DEFAULT_AMP_RELATIVE = 'after';
    const DEFAULT_AMP_SELECTORS = [ 'p:first-child', 'span:first-child' ];
    const DEFAULT_CONFIG = [
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

        $config = array_merge(
            self::DEFAULT_CONFIG,
            [
                'enabled' => $organic->getOption( 'organic::connatix_enabled' ),
                'playspaceId' => $organic->getOption( 'organic::connatix_playspace_id' ),
            ]
        );
        parent::__construct( $config );

        $this->enabled = $config['enabled'] ?: false;
        $this->playspaceId = $config['playspaceId'] ?: '';
        $this->organic = $organic;
    }

    public function isEnabled() {
        if (
            $this->organic->isEnabled() && $this->enabled && is_valid_uuid( $this->playspaceId )
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
