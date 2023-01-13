<?php

namespace App\Model\Planning;

class SprintSum
{
    public string $sprintId;
    public float $sumSeconds = 0.0;
    public float $sumHours = 0.0;

    public function __construct(string $sprintId)
    {
        $this->sprintId = $sprintId;
    }
}
