<?php

namespace Organic;

class BaseConfig {

    /**
     * Map (key -> config-for-placement) of configs for Placements
     *
     * @var array
     */
    public $forPlacement;

    /**
     * Raw Config returned from Organic Platform API
     *
     * @var array
     */
    public $raw;

    public function __construct( array $raw ) {
        if ( empty( $raw ) || ! is_array( $raw ) ) {
            $this->forPlacement = [];
            $this->raw = [];
            return;
        } else {
            $this->raw = $raw;
        }

        $this->forPlacement = isset( $this->raw['placements'] )
            ? array_reduce(
                $this->raw['placements'],
                function ( $byKey, $config ) {
                    $byKey[ $config['key'] ] = $config;
                    return $byKey;
                },
                []
            )
            : [];
    }
}
