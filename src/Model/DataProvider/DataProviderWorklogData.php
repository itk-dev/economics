<?php

namespace App\Model\DataProvider;

class DataProviderWorklogData
{
    public function __construct(
        public int $projectTrackerId,
        public int $dataProviderId,
        public string $projectTrackerIssueId,
        public ?string $description,
        public \DateTimeInterface $startedDate,
        public string $username,
        public float $hours,
        public ?string $kind,
        public ?\DateTimeInterface $fetchTime,
        public ?\DateTimeInterface $sourceModifiedDate,
        public bool $disableModifiedAtCheck = false,
        // True when $username is a stand-in the data provider invented because the source had
        // none, so it must not overwrite a real name already recorded for this worklog.
        public bool $usernameIsPlaceholder = false,
    ) {
    }
}
