<?php

namespace Organic;

class FbiaConfig extends BaseConfig {
    const MODE_DISABLED = 0;
    const MODE_AUTOMATIC = 1;
    const MODE_MANUAL = 2;

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
    }

    public static function validMode( int $mode ) {
        if ( $mode >= self::MODE_DISABLED && $mode <= self::MODE_MANUAL ) {
            return $mode;
        }
        return self::MODE_DISABLED;
    }
}
