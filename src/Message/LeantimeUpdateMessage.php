<?php

namespace App\Message;

use App\Entity\DataProvider;

readonly class LeantimeUpdateMessage
{
    public function __construct(
        public string $type,
        public int $start,
        public int $limit,
        public DataProvider $dataProvider,
        public ?array $projectTrackerProjectIds = null,
    ) {}
}
