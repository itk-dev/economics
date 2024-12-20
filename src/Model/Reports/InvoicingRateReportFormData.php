<?php

namespace App\Model\Reports;

class InvoicingRateReportFormData
{
    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public int $year;
    public bool $includeIssues;
}
