<?php

namespace Empire;

class AdsConfig extends BaseConfig {


    /**
     * List of AdRules returned from Empire Platform API
     * Each AdRule must contain at least:
     *  bool enabled
     *  string component
     *  string comparator
     *  string value
     *  (optional) array placementKeys
     */
    public array $adRules;

    /**
     * Map (key -> Placement) of Placements returned from Empire Platform API
     * Each Placement must contain at least:
     *  array[string] selectors
     *  int limit
     *  string relative
     */
    public array $forPlacement;

    public function __construct( array $raw ) {
        parent::__construct( $raw );
        if ( empty( $raw ) ) {
            $this->adRules = [];
            return;
        }

        $this->adRules = $raw['adRules'];
    }

    public function getPrebidBuildUrl() : string {
        $prebid = $this->raw['prebid'] ?? [];
        return $prebid['useBuild'] ?? 'https://empirecdn.io/assets/prebid5.13.0.js';
    }
}
