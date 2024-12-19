<?php

namespace App\Service;

use App\Model\Reports\InvoicingRateReportData;
use App\Model\Reports\InvoicingRateReportViewModeEnum;
use App\Model\Reports\InvoicingRateReportWorker;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;

class InvoicingRateReportService
{
    public function __construct(
        private readonly WorkerRepository $workerRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly DateTimeHelper $dateTimeHelper,
    ) {
    }

    /**
     * Retrieves the workload report data for the given view mode.
     *
     * @param PeriodTypeEnum $viewPeriodType The view period type (default: 'week')
     * @param InvoicingRateReportViewModeEnum $viewMode the view mode to generate the report for
     *
     * @return InvoicingRateReportData the workload report data
     *
     * @throws \Exception when the workload of a worker cannot be unset
     */
    public function getInvoicingRateReport(PeriodTypeEnum $viewPeriodType = PeriodTypeEnum::WEEK, InvoicingRateReportViewModeEnum $viewMode = InvoicingRateReportViewModeEnum::SUMMARY): InvoicingRateReportData
    {
        $invoicingRateReportData = new InvoicingRateReportData($viewPeriodType->value);
        $year = (int) (new \DateTime())->format('Y');
        $workers = $this->workerRepository->findAllIncludedInReports();
        $periods = $this->getPeriods($viewPeriodType, $year);
        $periodSums = [];
        $periodCounts = [];

        foreach ($periods as $period) {
            $readablePeriod = $this->getReadablePeriod($period, $viewPeriodType);
            $invoicingRateReportData->period->set((string) $period, $readablePeriod);
        }

        foreach ($workers as $worker) {
            $invoicingRateReportWorker = new InvoicingRateReportWorker();
            $invoicingRateReportWorker->setEmail($worker->getUserIdentifier());
            $invoicingRateReportWorker->setWorkload($worker->getWorkload());
            $invoicingRateReportWorker->setName($worker->getName());
            $currentPeriodReached = false;
            $loggedBilledHoursSum = 0;
            $loggedHoursSum = 0;
            $workerProjects = [];

            foreach ($periods as $period) {
                // Add current period match-point (current week-number, month-number etc.)
                $currentPeriodNumeric = $this->getCurrentPeriodNumeric($viewPeriodType);

                if ($period === $currentPeriodNumeric) {
                    $invoicingRateReportData->setCurrentPeriodNumeric($period);
                    $currentPeriodReached = true;
                }

                // Get first and last date in period.
                ['dateFrom' => $dateFrom, 'dateTo' => $dateTo] = $this->getDatesOfPeriod($period, $year, $viewPeriodType);
                $workerIdentifier = $worker->getUserIdentifier();

                if (empty($workerIdentifier)) {
                    throw new \Exception('Worker identifier cannot be empty');
                }

                [$worklogs, $billableWorklogs, $billedWorklogs] = $this->getWorklogs($viewMode, $workerIdentifier, $dateFrom, $dateTo);

                // Tally up logged hours in gathered worklogs for current period
                $loggedHours = 0;
                foreach ($worklogs as $worklog) {
                    $projectName = $worklog->getProject()->getName();
                    $issueName = $worklog->getIssue()->getName();
                    $workerProjects[$projectName][$period]['loggedHours'] = ($workerProjects[$projectName][$period]['loggedHours'] ?? 0) + ($worklog->getTimeSpentSeconds() / 60 / 60);
                    $workerProjects[$projectName][$issueName][$period]['loggedHours'] = ($workerProjects[$projectName][$issueName][$period]['loggedHours'] ?? 0) + ($worklog->getTimeSpentSeconds() / 60 / 60);
                    $workerProjects[$projectName][$issueName]['linkToissue'][$worklog->getIssue()->getProjectTrackerId()] = $worklog->getIssue()->getLinkToIssue();
                    $loggedHours += ($worklog->getTimeSpentSeconds() / 60 / 60);
                }

                // Tally up billable logged hours in gathered worklogs for current period
                $loggedBillableHours = 0;
                foreach ($billableWorklogs as $billableWorklog) {
                    $projectName = $billableWorklog->getProject()->getName();
                    $issueName = $billableWorklog->getIssue()->getName();
                    $workerProjects[$projectName][$period]['loggedBillableHours'] = ($workerProjects[$projectName][$period]['loggedBillableHours'] ?? 0) + ($billableWorklog->getTimeSpentSeconds() / 60 / 60);
                    $workerProjects[$projectName][$issueName][$period]['loggedBillableHours'] = ($workerProjects[$projectName][$issueName][$period]['loggedBillableHours'] ?? 0) + ($billableWorklog->getTimeSpentSeconds() / 60 / 60);
                    $workerProjects[$projectName][$issueName]['linkToissue'][$billableWorklog->getIssue()->getProjectTrackerId()] = $billableWorklog->getIssue()->getLinkToIssue();
                    $loggedBillableHours += ($billableWorklog->getTimeSpentSeconds() / 60 / 60);
                }

                // Tally up billed logged hours in gathered worklogs for current period
                $loggedBilledHours = 0;
                foreach ($billedWorklogs as $billedWorklog) {
                    $projectName = $billedWorklog->getProject()->getName();
                    $issueName = $billedWorklog->getIssue()->getName();
                    $workerProjects[$projectName][$period]['loggedBilledHours'] = ($workerProjects[$projectName][$period]['loggedBilledHours'] ?? 0) + ($billedWorklog->getTimeSpentSeconds() / 60 / 60);
                    $workerProjects[$projectName][$issueName][$period]['loggedBilledHours'] = ($workerProjects[$projectName][$issueName][$period]['loggedBilledHours'] ?? 0) + ($billedWorklog->getTimeSpentSeconds() / 60 / 60);
                    $workerProjects[$projectName][$issueName]['linkToissue'][$billedWorklog->getIssue()->getProjectTrackerId()] = $billedWorklog->getIssue()->getLinkToIssue();
                    $loggedBilledHours += ($billedWorklog->getTimeSpentSeconds() / 60 / 60);
                }

                // Count up sums until current period have been reached.
                if (!$currentPeriodReached) {
                    $loggedBilledHoursSum += $loggedBilledHours;
                    $loggedHoursSum += $loggedHours;
                }

                $loggedBilledPercentage = $loggedHours > 0 ? round($loggedBilledHours / $loggedHours * 100, 4) : 0;

                // Add percentage result to worker for current period.
                $invoicingRateReportWorker->dataByPeriod->set($period, [
                    'loggedBillableHours' => $loggedBillableHours,
                    'loggedBilledPercentage' => $loggedBilledPercentage,
                ]);

                // Increment the sum and count for this period
                $periodSums[$period] = ($periodSums[$period] ?? 0) + $loggedBilledPercentage;
                $periodCounts[$period] = ($periodCounts[$period] ?? 0) + 1;

                // Calculate and set the average for this period
                $average = round($periodSums[$period] / $periodCounts[$period], 4);

                $invoicingRateReportData->periodAverages->set($period, $average);
            }

            $invoicingRateReportWorker->average = $loggedHoursSum > 0 ? round($loggedBilledHoursSum / $loggedHoursSum * 100, 4) : 0;

            $invoicingRateReportData->workers->add($invoicingRateReportWorker);

            $invoicingRateReportWorker->projectData->set('projects', [
                $workerProjects ?? []
            ]);

        }



        // Calculate and set the total average
        $numberOfPeriods = count($invoicingRateReportData->periodAverages);

        // Calculate the sum of period averages
        $averageSum = array_reduce($invoicingRateReportData->periodAverages->toArray(), function ($carry, $item) {
            return $carry + $item;
        }, 0);

        // Calculate the total average of averages
        if ($numberOfPeriods > 0) {
            $invoicingRateReportData->totalAverage = round($averageSum / $numberOfPeriods, 4);
        }

        return $invoicingRateReportData;
    }

    /**
     * Retrieves the current period as a numeric value based on the given view mode.
     *
     * @param PeriodTypeEnum $viewMode the view mode to determine the current period
     *
     * @return int the current period as a numeric value
     */
    private function getCurrentPeriodNumeric(PeriodTypeEnum $viewMode): int
    {
        return match ($viewMode) {
            PeriodTypeEnum::MONTH => (int) (new \DateTime())->format('n'),
            PeriodTypeEnum::WEEK => (int) (new \DateTime())->format('W'),
            PeriodTypeEnum::YEAR => (int) (new \DateTime())->format('Y'),
        };
    }

    /**
     * Retrieves an array of dates for a given period based on the view mode.
     *
     * @param int $period the period for which to retrieve dates
     * @param int $year the year for the period
     * @param PeriodTypeEnum $viewMode the view mode to determine the dates of the period
     *
     * @return array an array of dates for the given period
     */
    private function getDatesOfPeriod(int $period, int $year, PeriodTypeEnum $viewMode): array
    {
        return match ($viewMode) {
            PeriodTypeEnum::MONTH => $this->dateTimeHelper->getFirstAndLastDateOfMonth($period, $year),
            PeriodTypeEnum::WEEK => $this->dateTimeHelper->getFirstAndLastDateOfWeek($period, $year),
            PeriodTypeEnum::YEAR => $this->dateTimeHelper->getFirstAndLastDateOfYear($year),
        };
    }

    /**
     * Retrieves the readable period based on the given period and view mode.
     *
     * @param int $period the period to be made readable
     * @param PeriodTypeEnum $viewMode the view mode to determine the format of the readable period
     *
     * @return string the readable period
     */
    private function getReadablePeriod(int $period, PeriodTypeEnum $viewMode): string
    {
        return match ($viewMode) {
            PeriodTypeEnum::MONTH => $this->dateTimeHelper->getMonthName($period),
            PeriodTypeEnum::WEEK, PeriodTypeEnum::YEAR => (string) $period,
        };
    }

    /**
     * Retrieves an array of periods based on the given view mode.
     *
     * @param PeriodTypeEnum $viewMode the view mode to determine the periods
     * @param int $year the year containing the periods
     *
     * @return array an array of periods
     */
    private function getPeriods(PeriodTypeEnum $viewMode, int $year): array
    {
        return match ($viewMode) {
            PeriodTypeEnum::MONTH => range(1, 12),
            PeriodTypeEnum::WEEK => $this->dateTimeHelper->getWeeksOfYear($year),
            PeriodTypeEnum::YEAR => [(int) (new \DateTime())->format('Y')],
        };
    }

    /**
     * Returns workloads based on the provided view mode, worker, and date range.
     *
     * @param InvoicingRateReportViewModeEnum $viewMode defines the view mode
     * @param string $workerIdentifier the worker's identifier
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array the list of workloads matching the criteria defined by the parameters
     */
    private function getWorklogs(InvoicingRateReportViewModeEnum $viewMode, string $workerIdentifier, \DateTime $dateFrom, \DateTime $dateTo): array
    {
        return match ($viewMode) {
            InvoicingRateReportViewModeEnum::SUMMARY => [
                $this->worklogRepository->findWorklogsByWorkerAndDateRange($workerIdentifier, $dateFrom, $dateTo),
                $this->worklogRepository->findBillableWorklogsByWorkerAndDateRange($workerIdentifier, $dateFrom, $dateTo),
                $this->worklogRepository->findBilledWorklogsByWorkerAndDateRange($workerIdentifier, $dateFrom, $dateTo),
            ],
        };
    }
}
