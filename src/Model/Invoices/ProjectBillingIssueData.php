<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ProjectBillingIssueData
{
    public readonly string $name;
    public readonly string $status;
    public readonly string $projectTrackerId;
    public readonly string $projectTrackerKey;

    public function __construct(string $name, string $status, string $projectTrackerId, string $projectTrackerKey)
    {
        $this->name = $name;
        $this->status = $status;
        $this->projectTrackerId = $projectTrackerId;
        $this->projectTrackerKey = $projectTrackerKey;
    }
}
