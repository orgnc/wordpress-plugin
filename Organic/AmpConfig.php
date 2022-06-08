<?php

namespace Organic;

class AmpConfig extends BaseConfig {


    /**
     * Map (key -> amp) of amps for placements returned from Organic Platform API
     * Each amp must contain at least:
     *  string html
     *
     * @var array
     */
    public $forPlacement;
}
