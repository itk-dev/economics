<?php

namespace App\Model\Invoices;

use App\Enum\IssueStatusEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class IssueData
{
    public \DateTime $started;
    public string $name;
    public ?IssueStatusEnum $status;
    public string $projectTrackerId;
    public string $projectTrackerKey;
    public ?string $accountId = null;
    public ?string $accountKey = null;
    public ?string $epicName = null;
    public ?string $epicKey = null;
    public array $epics;
    /** @var Collection<string, VersionData> */
    public Collection $versions;
    public ?\DateTime $resolutionDate = null;
    public string $projectId;
    public ?int $planHours;
    public ?int $hourRemaining;
    public ?\DateTime $dueDate = null;
    public ?string $worker;
    public ?string $linkToIssue;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }
}
