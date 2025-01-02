<?php

namespace App\Model\Reports;

class WorkloadReportFormData
{
    public int $year;
    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public WorkloadReportViewModeEnum $viewMode;
}
