<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class HourReportData
{
    public readonly string $id;
    public float $projectTotalSpent;
    public float $projectTotalEstimated;
    /** @var ArrayCollection<string, HourReportProjectTicket> */
    public ArrayCollection $projectTags;

    public function __construct(float $projectTotalSpent, float $projectTotalEstimated)
    {
        $this->projectTotalSpent = $projectTotalSpent;
        $this->projectTotalEstimated = $projectTotalEstimated;
        $this->projectTags = new ArrayCollection();
    }
}
