<?php

namespace App\Model\DataProvider;

use App\Enum\IssueStatusEnum;

class DataProviderIssueData
{
    public function __construct(
        public string $projectTrackerId,
        public int $dataProviderId,
        public string $projectTrackerProjectId,
        public string $name,
        public array $epics,
        public float $plannedHours,
        public float $remainingHours,
        public ?string $worker,
        public IssueStatusEnum $status,
        public ?\DateTimeInterface $dueDate,
        public ?\DateTimeInterface $resolutionDate,
        public ?\DateTimeInterface $fetchTime,
        public ?string $url,
        public ?\DateTimeInterface $sourceModifiedDate,
    ) {
    }
}
