<?php

namespace App\Model\Planning;

class Sprint
{
    public readonly string $sprintId;
    public readonly int $weeks;
    public readonly float $sprintGoalLow;
    public readonly float $sprintGoalHigh;
    public readonly string $displayName;

    public function __construct(string $sprintId, int $weeks, float $sprintGoalLow, float $sprintGoalHigh, string $displayName)
    {
        $this->sprintId = $sprintId;
        $this->weeks = $weeks;
        $this->sprintGoalLow = $sprintGoalLow;
        $this->sprintGoalHigh = $sprintGoalHigh;
        $this->displayName = $displayName;
    }
}
