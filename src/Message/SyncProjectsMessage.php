<?php

namespace App\Message;

class SyncProjectsMessage
{
    public function __construct(
        private readonly int $dataProviderId,
        private readonly int $jobId,
    ) {
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
