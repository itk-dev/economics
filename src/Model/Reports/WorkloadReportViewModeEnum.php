<?php

namespace App\Model\Reports;

enum WorkloadReportViewModeEnum: string
{
    case WORKLOAD = 'workload_percentage_logged';
    case BILLABLE = 'billable_percentage_logged';
}
