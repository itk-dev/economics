<?php

namespace App\Model\Reports;

use App\Entity\Worker;
use Doctrine\Common\Collections\ArrayCollection;

class WorkloadReportWorker extends Worker
{
    /** @var ArrayCollection<int, float> */
    public ArrayCollection $loggedPercentage;

    /** @var float */
    public float $average;

    public function __construct()
    {
        parent::__construct();
        $this->average = 0.0;
        $this->loggedPercentage = new ArrayCollection();
    }
}
