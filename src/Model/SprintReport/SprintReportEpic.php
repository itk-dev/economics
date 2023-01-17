<?php

namespace App\Model\SprintReport;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class SprintReportEpic
{
    public readonly string $id;
    public readonly string $name;
    public float $spentSum = 0;
    public float $remainingSum = 0;
    public float $originalEstimateSum = 0;
    public float $plannedWorkSum = 0;
    public Collection $sprints;
    public Collection $loggedWork;
    public Collection $remainingWork;

    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
        $this->loggedWork = new ArrayCollection();
        $this->sprints = new ArrayCollection();
        $this->remainingWork = new ArrayCollection();
    }
}
