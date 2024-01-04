<?php

namespace App\Model\SprintReport;

class SprintReportVersion
{
    public string $id;
    public string $headline;

    public function __construct(string $id, string $headline)
    {
        $this->id = $id;
        $this->headline = $headline;
    }
}
