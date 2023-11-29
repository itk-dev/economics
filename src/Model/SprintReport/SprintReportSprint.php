<?php

namespace App\Model\SprintReport;

class SprintReportSprint
{
    public string $id;
    public string $name;
    public SprintStateEnum $state;
    public ?int $startDateTimestamp;
    public ?int $endDateTimestamp;

    public function __construct(string $id, string $name, SprintStateEnum $state, ?int $startDateTimestamp, ?int $endDateTimestamp)
    {
        $this->id = $id;
        $this->name = $name;
        $this->state = $state;
        $this->startDateTimestamp = $startDateTimestamp;
        $this->endDateTimestamp = $endDateTimestamp;
    }
}
