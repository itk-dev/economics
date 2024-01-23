<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;

class Weeks
{
    /** @var ArrayCollection<string, Assignee> */
    public ArrayCollection $weekCollection;
    public int $weeks;
    public readonly float $sprintGoalLow;
    public readonly float $sprintGoalHigh;
    public string $displayName;
    public string $dateSpan;

    public function __construct(ArrayCollection $weekCollection, int $weeks, float $sprintGoalLow, float $sprintGoalHigh, string $displayName, string $dateSpan)
    {
        $this->weekCollection = $weekCollection;
        $this->weeks = $weeks;
        $this->sprintGoalLow = $sprintGoalLow;
        $this->sprintGoalHigh = $sprintGoalHigh;
        $this->displayName = $displayName;
        $this->dateSpan = $dateSpan;
    }
}
