<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class HourReportProjectTicket
{
    public readonly string $id;
    public readonly string $headline;
    public float $totalEstimated;
    public float $totalSpent;
    public ArrayCollection $timesheets;
    public ArrayCollection $projectTickets;

    public function __construct($id, $headline, $totalEstimated, $totalSpent)
    {
        $this->id = $id;
        $this->headline = $headline;
        $this->totalEstimated = $totalEstimated;
        $this->totalSpent = $totalSpent;
        $this->timesheets = new ArrayCollection();
        $this->projectTickets = new ArrayCollection();
    }
}
