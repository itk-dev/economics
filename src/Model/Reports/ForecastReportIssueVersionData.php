<?php

namespace App\Model\Reports;

class ForecastReportIssueVersionData
{
    public string $issueVersion;
    public string $issueVersionIdentifier;
    public float $invoiced = 0.0;
    public float $invoicedAndRecorded = 0.0;
    /** @var array<string, ForecastReportWorklogData> */
    public array $worklogs = [];

    public function __construct(string $issueVersion)
    {
        $this->issueVersion = $issueVersion;
    }
}
