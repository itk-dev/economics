<?php

namespace App\Model\Reports;

use App\Entity\DataProvider;

class WorkloadReportFormData
{
    public DataProvider $dataProvider;
    public WorkloadReportPeriodTypeEnum $viewPeriodType;
    public WorkloadReportViewModeEnum $viewMode;
}
