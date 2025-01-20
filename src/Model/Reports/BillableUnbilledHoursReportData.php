<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class BillableUnbilledHoursReportData
{
    public string $id;

    /** @var ArrayCollection<string, string> */
    public ArrayCollection $projectData;

    public function __construct()
    {
        $this->projectData = new ArrayCollection();
    }
}
