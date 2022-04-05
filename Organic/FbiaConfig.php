<?php

namespace Organic;

class FbiaConfig extends BaseConfig {
    const MODE_DISABLED = 0;
    const MODE_AUTOMATIC = 1;
    const MODE_MANUAL = 2;

    const AD_DENSITY_DEFAULT = 'default';
    const AD_DENSITY_MEDIUM = 'medium';
    const AD_DENSITY_LOW = 'low';

    const FB_AD_SOURCE_NONE = 'none';

    const DEFAULTS = [
        'mode' => self::MODE_DISABLED,
        'enabled' => false,
        'placements' => [],
    ];

    public int $mode;
    public bool $enabled;
    public string $adDensity = self::AD_DENSITY_DEFAULT;

    public function __construct( array $raw ) {
        $config = array_merge( self::DEFAULTS, $raw );
        parent::__construct( $config );
        $this->mode = self::getValidMode( $config['mode'] );
        $this->enabled = $config['enabled'];
    }

    public static function getValidMode( $mode ) {
        if ( ! $mode ) {
            return self::MODE_DISABLED;
        }

        if ( $mode >= self::MODE_DISABLED && $mode <= self::MODE_MANUAL ) {
            return $mode;
        }
        return self::MODE_DISABLED;
    }

    public function isAutomatic() {
        return $this->mode === self::MODE_AUTOMATIC;
    }

    public function isFacebookPluginInstalled() {
        return class_exists( 'Instant_Articles_Post' );
    }

    public function isFacebookPluginConfigured() {
        if ( ! $this->isFacebookPluginInstalled() ) {
            return false;
        }
        $settings_ads = \Instant_Articles_Option_Ads::get_option_decoded() ?? [];
        $ad_source = isset( $settings_ads['ad_source'] ) ? $settings_ads['ad_source'] : self::FB_AD_SOURCE_NONE;

        return $ad_source === self::FB_AD_SOURCE_NONE;
    }

    public function isEmpty() {
        return empty( $this->forPlacement );
    }

    public function isApplicable() {
        if (
            $this->enabled
            || $this->mode !== self::MODE_DISABLED
            || ! $this->isEmpty()
            || $this->isFacebookPluginInstalled()
            || $this->isFacebookPluginConfigured()
        ) {
            return true;
        }

        return false;
    }
}
