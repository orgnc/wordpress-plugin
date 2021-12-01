<?php

namespace Empire;

class Campaign {
    private $guid;
    private $name;
    private $status;
    private $externalId;

    public function __construct( $guid, $name, $status, $externalId ) {
        $this->guid = $guid;
        $this->name = $name;
        $this->status = $status;
        $this->externalId = $externalId;
    }

    public function getGuid() {
        return $this->guid;
    }

    public function getName() {
        return $this->name;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getExternalID() {
        return $this->externalId;
    }
}


class CampaignAsset {
    private $guid;
    private $name;
    private $externalId;
    private $campaign;

    public function __construct( $guid, $name, $externalId, Campaign $campaign ) {
        $this->guid = $guid;
        $this->name = $name;
        $this->externalId = $externalId;
        $this->campaign = $campaign;
    }

    public function getGuid() {
        return $this->guid;
    }

    public function getName() {
        return $this->name;
    }

    public function getExternalID() {
        return $this->externalId;
    }

    public function getCampaign() {
        return $this->campaign;
    }
}
