<?php

namespace App\Model\SprintReport;

class Epic
{
    public string $id;
    public string $name;
    public float $spentSum = 0;
    public float $remainingSum = 0;
    public float $originalEstimateSum = 0;
    public float $plannedWorkSum = 0;

    /**
     * @param string $id
     * @param string $name
     */
    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
