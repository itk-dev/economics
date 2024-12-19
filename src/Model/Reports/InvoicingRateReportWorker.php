<?php

namespace App\Model\Reports;

use App\Entity\Worker;
use Doctrine\Common\Collections\ArrayCollection;

class InvoicingRateReportWorker extends Worker
{
    /** @var float */
    public float $average;

    /** @var ArrayCollection<int, array> */
    public ArrayCollection $dataByPeriod;

    /** @var ArrayCollection<int, array> */
    public ArrayCollection $projectData;

    public function __construct()
    {
        parent::__construct();
        $this->average = 0.0;
        $this->dataByPeriod = new ArrayCollection();
        $this->projectData = new ArrayCollection();
    }
}
