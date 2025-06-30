<?php

namespace App\Message;

class SyncProjectWorklogsMessage
{
    public function __construct(
        private readonly int $projectId,
        private readonly int $dataProviderId,
        private readonly ?int $synchronizationJobId = null
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

    public function getSynchronizationJobId(): ?int
    {
        return $this->synchronizationJobId;
    }
}
