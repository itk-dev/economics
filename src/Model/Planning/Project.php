<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Project
{
    public readonly string $key;
    public readonly string $displayName;
    public Collection $assignees;
    public Collection $sprintSums;

    public function __construct(string $key, string $displayName)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->assignees = new ArrayCollection();
        $this->sprintSums = new ArrayCollection();
    }
}
