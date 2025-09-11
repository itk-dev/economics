<?php

namespace App\Message;

class SyncProjectsMessage
{
    public function __construct(
        private readonly int $dataProviderId,
    ) {
    }

    public function getDataProviderId(): int
    {
        return $this->dataProviderId;
    }

}
