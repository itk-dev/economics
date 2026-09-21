<?php

namespace App\Model\Reports;

use App\Entity\WorkerGroup;

class WorkloadReportFormData
{
    public int $year;
    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public WorkloadReportViewModeEnum $viewMode;
    public ?WorkerGroup $group = null;
}
