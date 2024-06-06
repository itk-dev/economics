<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class WorkloadReportData
{
    public readonly string $id;
    public readonly string $viewmode;
    /** @var ArrayCollection<string, string> */
    public ArrayCollection $period;
    /** @var ArrayCollection<string, WorkloadReportWorker> */
    public ArrayCollection $workers;

    public function __construct(string $viewmode)
    {
        $this->viewmode = $viewmode;
        $this->period = new ArrayCollection();
        $this->workers = new ArrayCollection();
    }
}
