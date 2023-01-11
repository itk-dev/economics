<?php

namespace App\Model\Planning;

class Sprint
{
    public string $sprintId;
    public int $weeks;
    public float $sprintGoalLow;
    public float $sprintGoalHigh;
    public string $displayName;

    /**
     * @param string $sprintId
     * @param int $weeks
     * @param float $sprintGoalLow
     * @param float $sprintGoalHigh
     * @param string $displayName
     */
    public function __construct(string $sprintId, int $weeks, float $sprintGoalLow, float $sprintGoalHigh, string $displayName)
    {
        $this->sprintId = $sprintId;
        $this->weeks = $weeks;
        $this->sprintGoalLow = $sprintGoalLow;
        $this->sprintGoalHigh = $sprintGoalHigh;
        $this->displayName = $displayName;
    }
}
