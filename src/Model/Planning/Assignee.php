<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Assignee
{
    public readonly string $key;
    public readonly string $displayName;
    public Collection $projects;
    public Collection $sprintSums;

    public function __construct(string $key, string $displayName)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->projects = new ArrayCollection();
        $this->sprintSums = new ArrayCollection();
    }
}
