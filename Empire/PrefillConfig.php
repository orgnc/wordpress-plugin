<?php

namespace Empire;

class PrefillConfig extends BaseConfig {


    /**
     * Map (key -> prefill) of prefills for placements returned from Empire Platform API
     * Each prefill must contain at least:
     *  string html
     *  string css
     */
    public array $forPlacement;
}
