<?php

namespace App\Service;

use App\Model\Reports\WorkloadReportData;
use App\Model\Reports\WorkloadReportPeriodTypeEnum as PeriodTypeEnum;
use App\Model\Reports\WorkloadReportViewModeEnum as ViewModeEnum;
use App\Model\Reports\WorkloadReportWorker;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;

class WorkloadReportService
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
     * @param ViewModeEnum $viewMode the view mode to generate the report for
     *
     * @return WorkloadReportData the workload report data
     *
     * @throws \Exception when the workload of a worker cannot be unset
     */
    public function getWorkloadReport(PeriodTypeEnum $viewPeriodType = PeriodTypeEnum::WEEK, ViewModeEnum $viewMode = ViewModeEnum::WORKLOAD): WorkloadReportData
    {
        $startTime = microtime(true);
        $workloadReportData = new WorkloadReportData($viewPeriodType->value);
        $year = (int) (new \DateTime())->format('Y');
        $workers = $this->workerRepository->findAll();
        $periods = $this->getPeriods($viewPeriodType, $year);

        foreach ($periods as $period) {
            $readablePeriod = $this->getReadablePeriod($period, $viewPeriodType);
            $workloadReportData->period->set((string) $period, $readablePeriod);
        }
        $periodsWithDatesArray = $this->getDatesOfPeriods($periods, $year, $viewPeriodType);

        // To get all worklogs, we need the first and last datetime of the year
        $firstDateFrom = current($periodsWithDatesArray)['dateFrom'];
        $lastDateTo = end($periodsWithDatesArray)['dateTo'];

        $allWorklogs = $this->getWorklogs($viewMode, $firstDateFrom, $lastDateTo);

        foreach ($workers as $worker) {
            $workloadReportWorker = new WorkloadReportWorker();
            $workloadReportWorker->setEmail($worker->getUserIdentifier());
            $workloadReportWorker->setWorkload($worker->getWorkload());
            $workloadReportWorker->setName($worker->getName());
            $currentPeriodReached = false;
            $expectedWorkloadSum = 0;
            $loggedHoursSum = 0;

            // Add current period match-point (current week-number, month-number etc.)
            $currentPeriodNumeric = $this->getCurrentPeriodNumeric($viewPeriodType);

            $workerIdentifier = $worker->getUserIdentifier();

            foreach ($periodsWithDatesArray as $period => $periodDates) {
                $dateFrom = clone $periodDates['dateFrom'];
                $dateTo = clone $periodDates['dateTo'];

                if ($period === $currentPeriodNumeric) {
                    $workloadReportData->setCurrentPeriodNumeric($period);
                    $currentPeriodReached = true;
                }

                // Get first and last date in period.
                if (empty($workerIdentifier)) {
                    throw new \Exception('Worker identifier cannot be empty');
                }
                $worklogs = $allWorklogs[$workerIdentifier] ?? [];


                // Tally up logged hours in gathered worklogs for current period
                $loggedHours = 0;
                foreach ($worklogs as $worklog) {
                    if ($worklog->getStarted() >= $dateFrom && $worklog->getStarted() <= $dateTo) {
                        $loggedHours += ($worklog->getTimeSpentSeconds() / 60 / 60);
                    }
                }

                $workerWorkload = $worker->getWorkload();
                if (!$workerWorkload) {
                    $workerId = $worker->getUserIdentifier();
                    throw new \Exception("Workload of worker: $workerId cannot be null when generating workload report.");
                }

                $expectedWorkload = $this->getExpectedWorkHours($workerWorkload, $viewPeriodType, $dateFrom, $dateTo);

                $roundedLoggedPercentage = round($loggedHours / $expectedWorkload * 100, 2);

                // Count up sums until current period have been reached.
                if (!$currentPeriodReached) {
                    $expectedWorkloadSum += $expectedWorkload;
                    $loggedHoursSum += $loggedHours;
                }

                // Add percentage result to worker for current period.
                $workloadReportWorker->loggedPercentage->set($period, $roundedLoggedPercentage);
            }

            $workloadReportWorker->average = $expectedWorkloadSum > 0 ? round($loggedHoursSum / $expectedWorkloadSum * 100, 2) : 0;

            $workloadReportData->workers->add($workloadReportWorker);
        }

        /*    $endTime = microtime(true);
            $executionTime = ($endTime - $startTime);
            die( "This script took $executionTime seconds to run.");*/
        return $workloadReportData;
    }

    private function getExpectedWorkHours(float $workloadWeekBase, PeriodTypeEnum $viewPeriodType, \DateTime $dateFrom, \DateTime $dateTo): float
    {
        return match ($viewPeriodType) {
            PeriodTypeEnum::WEEK => $workloadWeekBase,
            PeriodTypeEnum::MONTH, PeriodTypeEnum::YEAR => $workloadWeekBase / 5 * $this->dateTimeHelper->getWeekdaysBetween($dateFrom, $dateTo),
        };
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
     * @param array $periods
     * @param int $year the year for the period
     * @param PeriodTypeEnum $viewMode the view mode to determine the dates of the period
     *
     * @return array an array of dates for the given period
     */
    private function getDatesOfPeriods(array $periods, int $year, PeriodTypeEnum $viewMode): array
    {
        return match ($viewMode) {
            PeriodTypeEnum::MONTH => $this->dateTimeHelper->getFirstAndLastDatesOfMonths($periods, $year),
            PeriodTypeEnum::WEEK => $this->dateTimeHelper->getFirstAndLastDatesOfWeeks($periods, $year),
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
     * @param ViewModeEnum $viewMode defines the view mode
     * @param string $workerIdentifier the worker's identifier
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array the list of workloads matching the criteria defined by the parameters
     */
    private function getWorklogs(ViewModeEnum $viewMode, \DateTime $dateFrom, \DateTime $dateTo): array
    {
        return match ($viewMode) {
            ViewModeEnum::WORKLOAD => $this->worklogRepository->findWorklogsByDateRange($dateFrom, $dateTo),
            ViewModeEnum::BILLABLE => $this->worklogRepository->findBillableWorklogsByDateRange($dateFrom, $dateTo),
        };
    }
}
