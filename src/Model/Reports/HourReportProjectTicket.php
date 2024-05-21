<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class HourReportProjectTicket
{
    public readonly string $id;
    public readonly string $headline;
    public string $totalEstimated;
    public string $totalSpent;
    public ArrayCollection $timesheets;

    public function __construct($id, $headline, $totalEstimated, $totalSpent)
    {
        $this->id = $id;
        $this->headline = $headline;
        $this->totalEstimated = $totalEstimated;
        $this->totalSpent = $totalSpent;
        $this->timesheets = new ArrayCollection();
    }
}
