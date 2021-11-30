<?php

namespace Empire;

/**
 * Manages the ads.txt data and presentation
 *
 * @package Empire
 */
class Campaigns {
    /**
     * @var Empire
     */
    private $empire;

    const FIELD_EMPIRE_CAMPAIGN_ASSET_FIELD_KEY = 'field_empire_campaign_asset';

    public function __construct( Empire $empire ) {
        $this->empire = $empire;
        add_filter(
            'acf/load_field/key='.self::FIELD_EMPIRE_CAMPAIGN_ASSET_FIELD_KEY,
            [ $this, 'loadCampaignAssetChoices' ],
        );
    }

    public function loadCampaignAssetChoices( $field ) {
        $field['choices'] = [];

        $assets = $this->empire->loadCampaignsAssets();
        foreach ($assets as $asset) {
            $field['choices'][$asset['guid']] = (
                "#{$asset['externalId']} {$asset['name']} [{$asset['campaign']['name']}]"
            );
        }

        return $field;
    }
}