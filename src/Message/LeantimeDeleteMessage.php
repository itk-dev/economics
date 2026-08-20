<?php

namespace App\Message;

readonly class LeantimeDeleteMessage
{
    public function __construct(
        public string $type,
        public int $start,
        public int $limit,
        public int $dataProviderId,
        public bool $asyncJobQueue,
        public ?\DateTimeInterface $deletedAfter,
    ) {
    }
}
