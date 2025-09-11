<?php

namespace App\Message;

readonly class SyncProjectWorklogsMessage
{
    public function __construct(
        private int $projectId,
        private int $dataProviderId,
    ) {
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function getDataProviderId(): int
    {
        return $this->dataProviderId;
    }
}
