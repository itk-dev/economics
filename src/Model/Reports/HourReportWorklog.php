<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class HourReportWorklog
{
    public readonly ?int $id;
    public readonly float $hours;
    public ArrayCollection $projectTicket;

    public function __construct(?int $id, float $hours)
    {
        $this->id = $id;
        $this->hours = $hours;
        $this->projectTicket = new ArrayCollection();
    }
}
