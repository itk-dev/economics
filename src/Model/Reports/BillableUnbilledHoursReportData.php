<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class BillableUnbilledHoursReportData
{
    public string $id;

    /** @var ArrayCollection<string, mixed> */
    public ArrayCollection $projectData;
    public array $projectTotals;
    public int|float $totalHoursForAllProjects;

    public function __construct()
    {
        $this->projectData = new ArrayCollection();
    }
}
