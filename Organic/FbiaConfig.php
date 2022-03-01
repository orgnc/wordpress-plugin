<?php

namespace Organic;

class FbiaConfig extends BaseConfig {
    const MODE_DISABLED = 0;
    const MODE_AUTOMATIC = 1;
    const MODE_MANUAL = 2;

    const FB_AD_SOURCE_NONE = 'none';

    /**
     * Map (key -> prefill) of prefills for placements returned from Organic Platform API
     * Each prefill must contain at least:
     *  string html
     *  string css
     */
    public array $forPlacement;
    public int $mode;
    public bool $enabled;

    public function __construct( array $raw ) {
        parent::__construct( $raw );
        $this->mode = self::validMode( $raw['mode'] );
        $this->enabled = $raw['enabled'] ?? false;
        if ( ! $this->enabled ) {
            return;
        }

        if ( ! class_exists( 'Instant_Articles_Post' ) || empty( $this->forPlacement ) ) {
            $this->enabled = false;
            return;
        }

        $settings_ads = \Instant_Articles_Option_Ads::get_option_decoded() ?? [];
        $ad_source = isset( $settings_ads['ad_source'] ) ? $settings_ads['ad_source'] : self::FB_AD_SOURCE_NONE;

        if ( $ad_source !== self::FB_AD_SOURCE_NONE ) {
            $this->enabled = false;
        }
    }

    public static function validMode( int $mode ) {
        if ( $mode >= self::MODE_DISABLED && $mode <= self::MODE_MANUAL ) {
            return $mode;
        }
        return self::MODE_DISABLED;
    }
}
