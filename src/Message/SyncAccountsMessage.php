<?php

namespace App\Message;

class SyncAccountsMessage
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
