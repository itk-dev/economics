<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class ForecastReportData
{
    public float $totalInvoiced = 0;
    public float $totalInvoicedAndRecorded = 0;
    /** @var ArrayCollection<string, ForecastReportProjectData> */
    public ArrayCollection $projects;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }
}
