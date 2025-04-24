<?php

namespace App\Service;

use App\Model\Reports\BillableUnbilledHoursReportData;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class BillableUnbilledHoursReportService
{
    private const SECONDS_TO_HOURS = 1 / 3600;

    public function __construct(
        private readonly WorklogRepository $worklogRepository,
        private readonly DateTimeHelper $dateTimeHelper,
        private readonly WorkerRepository $workerRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getBillableUnbilledHoursReport(
        int $year,
        int $quarter,
    ): BillableUnbilledHoursReportData {
        $billableUnbilledHoursReportData = new BillableUnbilledHoursReportData();
    
        // If quarter is false, get the full year.
        ['dateFrom' => $dateFrom, 'dateTo' => $dateTo] = $quarter
            ? $this->dateTimeHelper->getFirstAndLastDateOfQuarter($year, $quarter)
            : $this->dateTimeHelper->getFirstAndLastDateOfYear($year);

        $billableWorklogs = $this->worklogRepository->findBillableWorklogsByWorkerAndDateRange($dateFrom, $dateTo, null, false);

        $projectData = [];
        $projectTotals = [];
        $totalHoursForAllProjects = 0;

        foreach ($billableWorklogs as $billableWorklog) {
            $projectName = $billableWorklog->getProject()->getName();
            $issueName = $billableWorklog->getIssue()->getName();

            // Initialize issue data if not already set
            if (!isset($projectData[$projectName][$issueName])) {
                $projectData[$projectName][$issueName] = [
                    'worklogs' => [],
                    'totalHours' => 0,
                    'id' => $billableWorklog->getIssue()->getProjectTrackerId(),
                    'linkToIssue' => $billableWorklog->getIssue()->getLinkToIssue(),
                ];
            }

            $workerIdentifier = $billableWorklog->getWorker();
            $workerName = $this->workerRepository->findOneBy(['email' => $workerIdentifier])?->getName() ?? '';

            // Add the worklog to the issue
            $projectData[$projectName][$issueName]['worklogs'][] = [
                'worker' => !empty($workerName) ? $workerName : $this->translator->trans('billable_unbilled_hours_report.no_worker'),
                'description' => !empty($billableWorklog->getDescription()) ? $billableWorklog->getDescription() : $this->translator->trans('billable_unbilled_hours_report.no_description'),
                'hours' => $billableWorklog->getTimeSpentSeconds() * self::SECONDS_TO_HOURS,
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

        // Add project data, project totals, and global total to the report data
        $billableUnbilledHoursReportData->projectData->add($projectData);
        $billableUnbilledHoursReportData->projectTotals = $projectTotals;
        $billableUnbilledHoursReportData->totalHoursForAllProjects = $totalHoursForAllProjects;

        return $billableUnbilledHoursReportData;
    }
}
