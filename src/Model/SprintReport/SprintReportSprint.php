<?php

namespace App\Model\SprintReport;

class SprintReportSprint
{
    public string $id;
    public string $name;
    // TODO: Replace with enum.
    public string $state;
    public ?int $startDateTimestamp;
    public ?int $endDateTimestamp;
    public ?int $completedDateTimestamp;

    /**
     * @param string $id
     * @param string $name
     * @param string $state
     * @param int|null $startDateTimestamp
     * @param int|null $endDateTimestamp
     * @param int|null $completedDateTimestamp
     */
    public function __construct(string $id, string $name, string $state, ?int $startDateTimestamp, ?int $endDateTimestamp, ?int $completedDateTimestamp)
    {
        $this->id = $id;
        $this->name = $name;
        $this->state = $state;
        $this->startDateTimestamp = $startDateTimestamp;
        $this->endDateTimestamp = $endDateTimestamp;
        $this->completedDateTimestamp = $completedDateTimestamp;
    }
}
