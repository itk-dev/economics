<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class HourReportProjectTag
{
    public float $totalEstimated;
    public float $totalSpent;
    public readonly string $tag;
    /** @var ArrayCollection<string, HourReportProjectTicket> */
    public ArrayCollection $projectTickets;

    public function __construct(float $totalEstimated, float $totalSpent, string $tag)
    {
        $this->totalEstimated = $totalEstimated;
        $this->totalSpent = $totalSpent;
        $this->tag = empty(trim($tag)) ? 'noTag' : $tag;
        $this->projectTickets = new ArrayCollection();
    }
}
