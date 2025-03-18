<?php

namespace App\Message;

class SynchronizeMessage
{
    public function __construct(private readonly int $synchronizationJobId)
    {
    }

    public function getSynchronizationJobId(): int
    {
        return $this->synchronizationJobId;
    }
}
