<?php

namespace Empire;

function empire_get_all_campaign_assets(): array {
    $result = [];
    $assets = Empire::getInstance()->loadCampaignsAssets();

    foreach ( $assets as $assetData ) {
        $campaignData = $assetData['campaign'];
        $asset = new CampaignAsset(
            $assetData['guid'],
            $assetData['name'],
            $assetData['externalId'],
            new Campaign(
                $campaignData['guid'],
                $campaignData['name'],
                $campaignData['status'],
                $campaignData['externalId'],
            ),
        );
        $result[] = $asset;
    }
    return $result;
}
