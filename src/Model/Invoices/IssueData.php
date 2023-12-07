<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class IssueData
{
    public \DateTime $started;
    public string $name;
    public string $status;
    public string $projectTrackerId;
    public string $projectTrackerKey;
    public ?string $accountId = null;
    public ?string $accountKey = null;
    public ?string $tagName = null;
    public ?string $tagKey = null;
    /** @var Collection<string, MilestoneData> */
    public Collection $milestones;
    public ?\DateTime $resolutionDate = null;
    public string $projectId;

    public function __construct()
    {
        $this->milestones = new ArrayCollection();
    }
}
