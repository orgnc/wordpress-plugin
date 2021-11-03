<?php

namespace Empire;

class AmpConfig extends BaseConfig {


    /**
     * Map (key -> amp) of amps for placements returned from Empire Platform API
     * Each amp must contain at least:
     *  string html
     */
    public array $forPlacement;
}
