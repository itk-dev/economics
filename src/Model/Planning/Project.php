<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Project
{
    public string $key;
    public string $displayName;
    public Collection $assignees;
    public Collection $sprintSums;

    /**
     * @param string $key
     * @param string $displayName
     */
    public function __construct(string $key, string $displayName)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->assignees = new ArrayCollection();
        $this->sprintSums = new ArrayCollection();
    }
}
