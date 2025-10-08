<?php

namespace App\Message;

readonly class LeantimeUpdateMessage
{
    public function __construct(
        public string $className,
        public int $start,
        public int $limit,
        public int $dataProviderId,
        public bool $asyncJobQueue,
        public bool $modified,
        public ?array $projectTrackerProjectIds = null,
    ) {}
}
