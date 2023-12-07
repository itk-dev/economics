<?php

namespace App\Model\Invoices;

class MilestoneData
{
    public readonly string $projectTrackerId;
    public readonly string $name;

    public function __construct(string $projectTrackerId, string $name)
    {
        $this->projectTrackerId = $projectTrackerId;
        $this->name = $name;
    }
}
