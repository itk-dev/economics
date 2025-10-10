<?php

namespace App\Model\Upsert;

class UpsertVersionData
{
    public function __construct(
        public int $dataProviderId,
        public string $name,
        public string $projectTrackerId,
        public string $projectTrackerProjectId,
        public ?\DateTimeInterface $fetchTime,
        public ?\DateTimeInterface $sourceModifiedDate,
    ) {}
}
