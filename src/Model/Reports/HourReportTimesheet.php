<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class HourReportTimesheet
{
    public readonly string $id;
    public readonly float $hours;
    public ArrayCollection $projectTicket;

    public function __construct(string $id, float $hours)
    {
        $this->id = $id;
        $this->hours = $hours;
        $this->projectTicket = new ArrayCollection();
    }
}
