<?php

namespace Organic;

class CampaignAsset {
    private $guid;
    private $name;
    private $externalId;
    private $startDate;
    private $endDate;
    private $campaign;

    public function __construct( $guid, $name, $externalId, $startDate, $endDate, Campaign $campaign ) {
        $this->guid = $guid;
        $this->name = $name;
        $this->externalId = $externalId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->campaign = $campaign;
    }

    public function getGUID() {
        return $this->guid;
    }

    public function getName() {
        return $this->name;
    }

    public function getExternalID() {
        return $this->externalId;
    }

    public function getStartDate() {
        return $this->startDate;
    }

    public function getEndDate() {
        return $this->endDate;
    }

    public function getCampaign() {
        return $this->campaign;
    }
}
