<?php

namespace App\Model\Reports;

class ForecastReportProjectData
{
    public string $projectId;
    public string $projectName;
    public float $invoiced = 0.0;
    public float $invoicedAndRecorded = 0.0;
    /** @var array<string, ForecastReportIssueData> */
    public array $issues = [];

    public function __construct(string $projectId)
    {
        $this->projectId = $projectId;
    }
}
