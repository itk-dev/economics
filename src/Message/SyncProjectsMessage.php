<?php

namespace App\Message;

readonly class SyncProjectsMessage
{
    public function __construct(
        private int $dataProviderId,
    ) {
    }

    public function getDataProviderId(): int
    {
        return $this->dataProviderId;
    }
}
