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
    public $adRules;

    /**
     * Map (key -> Placement) of Placements returned from Organic Platform API
     * Each Placement must contain at least:
     *  array[string] selectors
     *  int limit
     *  string relative
     */
    public $forPlacement;

    public $adsRefreshRates;

    private $fallbackUrl;
    private $prebidUrl;

    public function __construct( array $rawAdsConfig, array $rawAdsRefreshRates ) {
        parent::__construct( $rawAdsConfig );

        $this->fallbackUrl = Organic::getInstance()->sdk->getFallbackPrebidBuildUrl();

        $this->adsRefreshRates = $rawAdsRefreshRates;
        if ( ! empty( $rawAdsRefreshRates ) ) {
            $this->raw['adsRefreshRates'] = $rawAdsRefreshRates;
        }
        if ( empty( $rawAdsConfig ) ) {
            $this->adRules = [];
            $this->adsRefreshRates = [];
            $this->prebidUrl = $this->fallbackUrl;
            return;
        }

        $this->adRules = $rawAdsConfig['adRules'];

        $prebid = $rawAdsConfig['prebid'] ?? [];
        $this->prebidUrl = $prebid['useBuild'] ?? $this->fallbackUrl;
    }

    public function getPrebidBuildUrl() : string {
        return $this->prebidUrl;
    }
}
