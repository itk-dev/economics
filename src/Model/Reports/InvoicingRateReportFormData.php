<?php

namespace App\Model\Reports;

class InvoicingRateReportFormData
{
    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public InvoicingRateReportViewModeEnum $viewMode;
    public int $year;
    public bool $includeIssues;
}
