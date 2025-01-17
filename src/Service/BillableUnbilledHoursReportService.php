<?php

namespace App\Service;

use App\Model\Reports\BillableUnbilledHoursReportData;
use App\Repository\WorklogRepository;

class BillableUnbilledHoursReportService
{

    private const SECONDS_TO_HOURS = 1 / 3600;

    public function __construct(
        private readonly WorklogRepository $worklogRepository,
        private readonly DateTimeHelper $dateTimeHelper,
    ) {
    }


    public function getBillableUnbilledHoursReport(
        int $year,
    ): BillableUnbilledHoursReportData {

        $billableUnbilledHoursReportData = new BillableUnbilledHoursReportData();
        ['dateFrom' => $dateFrom, 'dateTo' => $dateTo] = $this->dateTimeHelper->getFirstAndLastDateOfYear($year);

        $billableWorklogs = $this->worklogRepository->findBillableWorklogsByWorkerAndDateRange($dateFrom, $dateTo);

        $projectData = [];
        $projectTotals = []; // To store total hours per project
        $totalHoursForAllProjects = 0; // To store the global total hours across all projects

        foreach ($billableWorklogs as $billableWorklog) {
            if ($billableWorklog->isBilled() === false) {
                $projectName = $billableWorklog->getProject()->getName();
                $issueName = $billableWorklog->getIssue()->getName();

                // Initialize issue data if not already set
                if (!isset($projectData[$projectName][$issueName])) {
                    $projectData[$projectName][$issueName] = [
                        'worklogs' => [],
                        'totalHours' => 0
                    ];
                }

                $workerIdentifier = $billableWorklog->getWorker();
                $workerName = $this->
                // Add the worklog to the issue
                $projectData[$projectName][$issueName]['worklogs'][] = [
                    "worker" => $billableWorklog->getWorker(),
                    "description" => $billableWorklog->getDescription(),
                    "hours" => $billableWorklog->getTimeSpentSeconds() * self::SECONDS_TO_HOURS
                ];

                // Increment the issue total hours
                $projectData[$projectName][$issueName]['totalHours'] += $billableWorklog->getTimeSpentSeconds() * self::SECONDS_TO_HOURS;

                // Initialize project total if not already set
                if (!isset($projectTotals[$projectName])) {
                    $projectTotals[$projectName] = 0;
                }

                // Add to the project total hours
                $projectTotals[$projectName] += $billableWorklog->getTimeSpentSeconds() * self::SECONDS_TO_HOURS;

                // Add to the global total hours
                $totalHoursForAllProjects += $billableWorklog->getTimeSpentSeconds() * self::SECONDS_TO_HOURS;
            }
        }

        // Add project data, project totals, and global total to the report data
        $billableUnbilledHoursReportData->projectData->add($projectData);
        $billableUnbilledHoursReportData->projectTotals = $projectTotals;
        $billableUnbilledHoursReportData->totalHoursForAllProjects = $totalHoursForAllProjects;

        return $billableUnbilledHoursReportData;
    }

}
