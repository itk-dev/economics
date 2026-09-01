<?php

namespace App\Model\Reports;

use App\Entity\Worklog;

/**
 * The worklogs behind a single cell of the workload report.
 */
class WorkloadReportPeriodWorklogsData
{
    /**
     * @param Worklog[] $worklogs
     */
    public function __construct(
        public readonly string $workerName,
        public readonly string $readablePeriod,
        public readonly int $year,
        public readonly WorkloadReportPeriodTypeEnum $viewPeriodType,
        public readonly WorkloadReportViewModeEnum $viewMode,
        public readonly float $expectedWorkload,
        public readonly float $loggedHours,
        public readonly float $loggedPercentage,
        public readonly array $worklogs,
    ) {
    }
}
