<?php

namespace App\Message;

use App\Entity\DataProvider;

readonly class LeantimeUpdateMessage
{
    public function __construct(
        public string $className,
        public int $start,
        public int $limit,
        public DataProvider $dataProvider,
        public bool $asyncJobQueue,
        public bool $modified,
        public ?array $projectTrackerProjectIds = null,
    ) {}
}
