<?php

namespace Organic;

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

    public function getGUID() {
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
