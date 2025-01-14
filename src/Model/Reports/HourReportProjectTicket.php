<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class HourReportProjectTicket
{
    public readonly string $id;
    public readonly string $projectTrackerId;
    public readonly string $headline;
    public float $totalEstimated;
    public float $totalSpent;
    public readonly string $linkToIssue;
    public ArrayCollection $timesheets;
    public ArrayCollection $projectTickets;

    public function __construct($id, $projectTrackerId, $headline, $totalEstimated, $totalSpent, $linkToIssue)
    {
        $this->id = $id;
        $this->projectTrackerId = $projectTrackerId;
        $this->headline = $headline;
        $this->totalEstimated = $totalEstimated;
        $this->totalSpent = $totalSpent;
        $this->linkToIssue = $linkToIssue;
        $this->timesheets = new ArrayCollection();
        $this->projectTickets = new ArrayCollection();
    }
}
