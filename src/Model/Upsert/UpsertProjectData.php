<?php

namespace App\Model\Upsert;

class UpsertProjectData
{
    public function __construct(
        public int $dataProviderId,
        public string $name,
        public string $projectTrackerId,
        public ?string $url,
        public ?\DateTimeInterface $fetchTime,
        public ?\DateTimeInterface $sourceModifiedDate,
    ) {
    }
}
