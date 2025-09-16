<?php

namespace App\Message;

class SyncProjectIssuesMessage
{
    public function __construct(
        private readonly string $projectId,
        private readonly int $dataProviderId,g
    ) {
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getDataProviderId(): string
    {
        return $this->dataProviderId;
    }
}
