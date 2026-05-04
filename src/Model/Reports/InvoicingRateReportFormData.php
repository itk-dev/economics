<?php

namespace App\Model\Reports;

class InvoicingRateReportFormData implements HasGroupFilter
{
    use HasGroupFilterTrait;

    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public int $year;
    public bool $includeIssues;
}
