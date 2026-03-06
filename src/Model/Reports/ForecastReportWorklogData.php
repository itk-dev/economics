<?php

namespace App\Model\Reports;

class ForecastReportWorklogData
{
    public string $description;
    public string $worker;
    public float $invoiced = 0.0;
    public float $invoicedAndRecorded = 0.0;

    public function __construct($worklogId, $description)
    {
    }
}
