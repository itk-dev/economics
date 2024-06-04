<?php

namespace App\Model\Reports;

use App\Entity\Worker;

class WorkloadReportWorker extends Worker
{
    public float $hoursLogged;

    public function __construct()
    {
    }

    public function getHoursLogged(): float
    {
        return $this->hoursLogged;
    }

    public function setHoursLogged(float $hoursLogged): void
    {
        $this->hoursLogged = $hoursLogged;
    }

}
