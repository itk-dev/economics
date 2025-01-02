<?php

namespace App\Model\Reports;

use App\Entity\DataProvider;

class WorkloadReportFormData
{
    public int $year;
    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public WorkloadReportViewModeEnum $viewMode;
}
