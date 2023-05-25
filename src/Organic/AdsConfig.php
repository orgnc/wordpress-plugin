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
    private $MODERN_PREBID_RE = '/.*sdk\/(prebid|prebid-stable|prebid-canary)\.js/';

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

    public function getPrebidBuildUrl( string $type = 'default' ) : string {
        if ( $type === 'default' ) {
            return $this->prebidUrl;
        }

        $moduleSrc = '';
        if ( preg_match( $this->MODERN_PREBID_RE, $this->prebidUrl ) ) {
            $moduleSrc = str_replace( '.js', '.m.js', $this->prebidUrl );
        };
        return $moduleSrc;
    }
}
