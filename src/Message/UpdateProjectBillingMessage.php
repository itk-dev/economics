<?php

namespace App\Message;

class UpdateProjectBillingMessage
{

    public function __construct(private int $projectBillingId)
    {
    }

    public function getProjectBillingId(): int
    {
        return $this->projectBillingId;
    }
}
