<?php

namespace App\Model\Invoices;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class WorklogData
{
    public string $projectTrackerId;
    public string $comment;
    public string $worker;
    public int $timeSpentSeconds;
    public DateTime $started;
    public string $issueName;
    public string $projectTrackerIssueId;
    public string $projectTrackerIssueKey;
    public ?string $epicName;
    public ?string $epicKey;
    /** @var Collection<VersionData> */
    public Collection $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }
}
