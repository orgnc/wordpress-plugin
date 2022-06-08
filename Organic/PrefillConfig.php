<?php

namespace Organic;

class PrefillConfig extends BaseConfig {
    /**
     * Map (key -> prefill) of prefills for placements returned from Organic Platform API
     * Each prefill must contain at least:
     *  string html
     *  string css
     *
     * @var array
     */
    public $forPlacement;
}
