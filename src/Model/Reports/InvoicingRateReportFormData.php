<?php

namespace App\Model\Reports;

use App\Entity\WorkerGroup;

class InvoicingRateReportFormData
{
    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public int $year;
    public bool $includeIssues;
    public ?WorkerGroup $group = null;
}
