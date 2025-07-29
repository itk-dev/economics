<?php

namespace App\Message;

class SyncProjectIssuesMessage
{
    public function __construct(
        private readonly int $projectId,
        private readonly int $dataProviderId,
        private readonly int $jobId,
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

    public function getJobId(): int
    {
        return $this->jobId;
    }
}
