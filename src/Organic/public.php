<?php

namespace Organic;

function organic_get_all_campaign_assets(): array {
    $result = [];
    $assets = Organic::getInstance()->loadCampaignsAssets();

    foreach ( $assets as $assetData ) {
        $campaignData = $assetData['campaign'];
        $startDate = date_create( $assetData['startDate'] );
        $endDate = date_create( $assetData['endDate'] );
        $asset = new CampaignAsset(
            $assetData['guid'],
            $assetData['name'],
            $assetData['externalId'],
            $startDate,
            $endDate,
            new Campaign(
                $campaignData['guid'],
                $campaignData['name'],
                $campaignData['status'],
                $campaignData['externalId']
            )
        );
        $result[] = $asset;
    }
    return $result;
}

function organic_content_assign_campaign_asset( $post_id, $campaign_asset_guid ) {
    Organic::getInstance()->assignContentCampaignAsset( $post_id, $campaign_asset_guid );
}

function organic_campaigns_enabled(): bool {
    return Organic::getInstance()->useCampaigns();
}
