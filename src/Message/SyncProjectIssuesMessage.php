<?php

namespace App\Message;

class SyncProjectIssuesMessage
{
    public function __construct(
        private readonly string $projectId,
        private readonly int $dataProviderId,
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
