<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;

class Assignee
{
    public readonly string $key;
    public readonly string $displayName;
    /** @var ArrayCollection<string, AssigneeProject> */
    public ArrayCollection $projects;
    /** @var ArrayCollection<string, SprintSum> */
    public ArrayCollection $sprintSums;
    public readonly ?float $weekNorm;

    public function __construct(string $key, string $displayName, ?float $weekNorm = null)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->weekNorm = $weekNorm;
        $this->projects = new ArrayCollection();
        $this->sprintSums = new ArrayCollection();
    }
}
