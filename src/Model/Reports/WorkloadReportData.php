<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class WorkloadReportData
{
    public readonly string $id;
    /** @var ArrayCollection<string, WorkloadReportWorker> */
    public ArrayCollection $workers;

    public function __construct()
    {
        $this->workers = new ArrayCollection();
    }
}
