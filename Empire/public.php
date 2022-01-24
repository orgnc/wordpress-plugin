<?php

namespace Empire;

function empire_get_all_campaign_assets(): array {
    $result = [];
    $assets = Empire::getInstance()->loadCampaignsAssets();

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
                $campaignData['externalId'],
            ),
        );
        $result[] = $asset;
    }
    return $result;
}

function empire_content_assign_campaign_asset( $post_id, ?string $campaign_asset_guid ): void {
    Empire::getInstance()->assignContentCampaignAsset( $post_id, $campaign_asset_guid );
}

function empire_campaigns_enabled(): bool {
    return Empire::getInstance()->isCampaignsAppEnabled();
}
