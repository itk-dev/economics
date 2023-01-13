<?php

namespace App\Model\SprintReport;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class SprintReportData
{
    public array $data;
    public Collection $epics;
    public Collection $sprints;
    public Collection $issues;
    public float $spentSum;
    public float $spentHours;
    public float $remainingHours;
    public float $projectHours;
    public string $projectName;
    public string $versionName;
    public float $originaltEstimatSum = 0.0;

    public function __construct()
    {
        $this->epics = new ArrayCollection();
        $this->sprints = new ArrayCollection();
        $this->issues = new ArrayCollection();
    }
}
