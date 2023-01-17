<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class AssigneeProject
{
    public readonly string $key;
    public readonly string $displayName;
    public Collection $sprintSums;
    public Collection $issues;

    public function __construct(string $key, string $displayName)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->sprintSums = new ArrayCollection();
        $this->issues = new ArrayCollection();
    }
}
