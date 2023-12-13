<?php

namespace App\Model\SprintReport;

class SprintReportVersion
{
    public string $id;
    public string $name;
    public string $projectTrackerId;

    public function __construct(string $id, string $name, string $projectTrackerId)
    {
        $this->id = $id;
        $this->name = $name;
        $this->projectTrackerId = $projectTrackerId;
    }
}
