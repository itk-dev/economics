<?php

namespace App\Message;

readonly class SyncProjectWorklogsMessage
{
    public function __construct(
        private string $projectId,
        private int $dataProviderId,
    ) {
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getDataProviderId(): int
    {
        return $this->dataProviderId;
    }
}
