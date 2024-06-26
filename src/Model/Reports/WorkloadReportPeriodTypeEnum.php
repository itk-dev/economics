<?php

namespace App\Model\Reports;

enum WorkloadReportPeriodTypeEnum: string
{
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';
}
