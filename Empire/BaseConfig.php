<?php

namespace Empire;

class BaseConfig {

    /**
     * Map (key -> config-for-placement) of configs for Placements
     */
    public array $forPlacement;

    /**
     * Raw Config returned from Empire Platform API
     */
    public array $raw;

    public function __construct( array $raw ) {
        if ( empty( $raw ) ) {
            $this->forPlacement = [];
            $this->raw = [];
            return;
        }

        $forPlacement = array_reduce(
            $raw['placements'],
            function ( $byKey, $config ) {
                $byKey[ $config['key'] ] = $config;
                return $byKey;
            },
            []
        );

        $this->forPlacement = $forPlacement;
        $this->raw = $raw;
    }
}
