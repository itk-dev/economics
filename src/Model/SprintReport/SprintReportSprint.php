<?php

namespace App\Model\SprintReport;

class SprintReportSprint
{
    public string $id;
    public string $name;
    public SprintStateEnum $state;

    public function __construct(string $id, string $name, SprintStateEnum $state)
    {
        $this->id = $id;
        $this->name = $name;
        $this->state = $state;
    }
}
