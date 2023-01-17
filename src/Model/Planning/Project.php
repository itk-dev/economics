<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;

class Project
{
    public readonly string $key;
    public readonly string $displayName;
    /** @var ArrayCollection<string, Assignee> */
    public ArrayCollection $assignees;
    /** @var ArrayCollection<string, SprintSum> */
    public ArrayCollection $sprintSums;

    public function __construct(string $key, string $displayName)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->assignees = new ArrayCollection();
        $this->sprintSums = new ArrayCollection();
    }
}
