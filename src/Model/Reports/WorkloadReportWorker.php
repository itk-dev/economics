<?php

namespace App\Model\Reports;

use App\Entity\Worker;
use Doctrine\Common\Collections\ArrayCollection;

class WorkloadReportWorker extends Worker
{
    /** @var ArrayCollection<int, float> */
    public ArrayCollection $loggedPercentage;

    public function __construct()
    {
        parent::__construct();
        $this->loggedPercentage = new ArrayCollection();
    }
}
