<?php

namespace App\Model\Reports;

class WorkloadReportFormData implements HasGroupFilter
{
    use HasGroupFilterTrait;

    public int $year;
    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public WorkloadReportViewModeEnum $viewMode;
}
