<?php

namespace App\Message;

readonly class LeantimeDeleteMessage
{
    public function __construct(
        public int $dataProviderId,
        public bool $asyncJobQueue,
        public ?\DateTimeInterface $deletedAfter,
    ) {
    }
}
