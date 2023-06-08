<?php

namespace App\Message;

class CreateProjectBillingMessage
{

    public function __construct(private int $projectBillingId)
    {
    }

    public function getProjectBillingId(): int
    {
        return $this->projectBillingId;
    }
}
