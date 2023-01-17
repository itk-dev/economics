<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;

class AssigneeProject
{
    public readonly string $key;
    public readonly string $displayName;
    /** @var ArrayCollection<string, SprintSum> */
    public ArrayCollection $sprintSums;
    /** @var ArrayCollection<string, Issue> */
    public ArrayCollection $issues;

    public function __construct(string $key, string $displayName)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->sprintSums = new ArrayCollection();
        $this->issues = new ArrayCollection();
    }
}
