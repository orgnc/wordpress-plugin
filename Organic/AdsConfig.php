<?php

namespace Organic;

class AdsConfig extends BaseConfig {


    /**
     * List of AdRules returned from Organic Platform API
     * Each AdRule must contain at least:
     *  bool enabled
     *  string component
     *  string comparator
     *  string value
     *  (optional) array placementKeys
     */
    public array $adRules;

    /**
     * Map (key -> Placement) of Placements returned from Organic Platform API
     * Each Placement must contain at least:
     *  array[string] selectors
     *  int limit
     *  string relative
     */
    public array $forPlacement;

    private string $fallbackUrl;
    private string $prebidUrl;

    public function __construct( array $raw ) {
        parent::__construct( $raw );

        $this->fallbackUrl = Organic::getInstance()->sdk->getFallbackPrebidBuildUrl();

        if ( empty( $raw ) ) {
            $this->adRules = [];
            $this->prebidUrl = $this->fallbackUrl;
            return;
        }

        $this->adRules = $raw['adRules'];

        $prebid = $this->raw['prebid'] ?? [];
        $this->prebidUrl = $prebid['useBuild'] ?? $this->fallbackUrl;
    }

    public function getPrebidBuildUrl() : string {
        return $this->prebidUrl;
    }
}
