<?php

namespace App\Model\Reports;

class ForecastReportIssueData
{
    public string $issueId;
    public string $issueTag;
    public string $issueLink;
    public float $invoiced = 0.0;
    public float $invoicedAndRecorded = 0.0;
    /** @var array<string, ForecastReportIssueVersionData> */
    public array $versions = [];

    public function __construct(string $issueTag)
    {
        $this->issueTag = $issueTag;
    }
}
