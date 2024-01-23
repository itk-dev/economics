<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;

class Weeks
{
    /** @var ArrayCollection<int, int> */
    public ArrayCollection $weekCollection;
    public int $weeks;
    public readonly float $sprintGoalLow;
    public readonly float $sprintGoalHigh;
    public mixed $displayName;
    public string $dateSpan;

    public function __construct(ArrayCollection $weekCollection, int $weeks, float $sprintGoalLow, float $sprintGoalHigh, mixed $displayName, string $dateSpan)
    {
        $this->weekCollection = $weekCollection;
        $this->weeks = $weeks;
        $this->sprintGoalLow = $sprintGoalLow;
        $this->sprintGoalHigh = $sprintGoalHigh;
        $this->displayName = $displayName;
        $this->dateSpan = $dateSpan;
    }
}
