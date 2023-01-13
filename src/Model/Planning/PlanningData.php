<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class PlanningData
{
    public Collection $assignees;
    public Collection $projects;
    public Collection $sprints;

    public function __construct()
    {
        $this->assignees = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->sprints = new ArrayCollection();
    }
}
