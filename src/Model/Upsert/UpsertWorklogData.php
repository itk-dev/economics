<?php

namespace App\Model\Upsert;

class UpsertWorklogData
{
    public function __construct(
        public int $projectTrackerId,
        public int $dataProviderId,
        public string $projectTrackerIssueId,
        public ?string $description,
        public \DateTimeInterface $startedDate,
        public string $username,
        public float $hours,
        public string $kind,
        public ?\DateTimeInterface $fetchTime,
        public ?\DateTimeInterface $sourceModifiedDate,
    ) {
    }
}
