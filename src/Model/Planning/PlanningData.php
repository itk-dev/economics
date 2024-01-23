<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;

class PlanningData
{
    /** @var ArrayCollection<string, Assignee> */
    public ArrayCollection $assignees;
    /** @var ArrayCollection<string, Project> */
    public ArrayCollection $projects;
    /** @var ArrayCollection<string, Sprint> */
    public ArrayCollection $sprints;
    /** @var ArrayCollection<string, Weeks> */
    public ArrayCollection $weeks;

    public function __construct()
    {
        $this->assignees = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->sprints = new ArrayCollection();
        $this->weeks = new ArrayCollection();
    }
}
