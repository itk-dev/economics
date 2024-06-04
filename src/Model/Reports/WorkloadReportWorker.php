<?php

namespace App\Model\Reports;

use App\Entity\Worker;
use Doctrine\Common\Collections\ArrayCollection;

class WorkloadReportWorker extends Worker
{
    /** @var ArrayCollection<int, float> */
    public arrayCollection $hoursLogged;


    public function __construct()
    {
        $this->hoursLogged = new ArrayCollection();
    }

    public function getHoursLogged(): ArrayCollection
    {
        return $this->hoursLogged;
    }

}
