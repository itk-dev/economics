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
     * Generates a workload report for a specified year and period type.
     *
     * This method computes the workload report for all workers based on the
     * specified year, period type, and view mode. It calculates various metrics
     * such as logged hours, expected workload, work percentage for each period,
     * average workloads, and overall summary statistics.
     *
     * @param int $year the year for which the workload report is generated
     * @param PeriodTypeEnum $viewPeriodType the period type (e.g., week, month, year) for the report
     * @param ViewModeEnum $viewMode the mode of viewing the workload (e.g., workload vs other modes)
     *
     * @return WorkloadReportData an object containing the workload report data
     *
     * @throws \Exception if a worker identifier is empty or the workload of a worker is null
     */
    public function getWorkloadReport(int $year, PeriodTypeEnum $viewPeriodType = PeriodTypeEnum::WEEK, ViewModeEnum $viewMode = ViewModeEnum::WORKLOAD): WorkloadReportData
    {
        $workloadReportData = new WorkloadReportData($viewPeriodType->value);
        if (!$year) {
            $year = (int) (new \DateTime())->format('Y');
        }
        $workers = $this->workerRepository->findAll();
        $periods = $this->getPeriods($viewPeriodType, $year);
        $periodSums = [];
        $periodCounts = [];

        foreach ($periods as $period) {
            $readablePeriod = $this->getReadablePeriod($period, $viewPeriodType);
            $workloadReportData->period->set((string) $period, $readablePeriod);
        }

        foreach ($workers as $worker) {
            $workloadReportWorker = new WorkloadReportWorker();
            $workloadReportWorker->setEmail($worker->getUserIdentifier());
            $workloadReportWorker->setWorkload($worker->getWorkload());
            $workloadReportWorker->setName($worker->getName());
            $currentPeriodReached = false;
            $expectedWorkloadSum = 0;
            $loggedHoursSum = 0;
            $periodSums = [];
            $periodCounts = [];

            foreach ($periods as $period) {
                // Add current period match-point (current week-number, month-number etc.)
                $currentPeriodNumeric = $this->getCurrentPeriodNumeric($viewPeriodType);

                if ($period === $currentPeriodNumeric) {
                    $workloadReportData->setCurrentPeriodNumeric($period);
                    $currentPeriodReached = true;
                }

                // Get first and last date in period.
                ['dateFrom' => $dateFrom, 'dateTo' => $dateTo] = $this->getDatesOfPeriod($period, $year, $viewPeriodType);
                $workerIdentifier = $worker->getUserIdentifier();

                if (empty($workerIdentifier)) {
                    throw new \Exception('Worker identifier cannot be empty');
                }

                $worklogs = $this->getWorklogs($viewMode, $workerIdentifier, $dateFrom, $dateTo);

                // Tally up logged hours in gathered worklogs for current period
                $loggedHours = 0;
                foreach ($worklogs as $worklog) {
                    $loggedHours += ($worklog->getTimeSpentSeconds() / 60 / 60);
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

                // Increment the sum and count for this period
                $periodSums[$period] = ($periodSums[$period] ?? 0) + $roundedLoggedPercentage;
                $periodCounts[$period] = ($periodCounts[$period] ?? 0) + 1;

                // Calculate and set the average for this period
                $average = round($periodSums[$period] / $periodCounts[$period], 2);
                $workloadReportData->periodAverages->set($period, $average);
            }

            $workloadReportWorker->average = $expectedWorkloadSum > 0 ? round($loggedHoursSum / $expectedWorkloadSum * 100, 2) : 0;

            $workloadReportData->workers->add($workloadReportWorker);
        }

        // Calculate and set the total average
        $numberOfPeriods = count($workloadReportData->periodAverages);

        // Calculate the sum of period averages
        $averageSum = array_reduce($workloadReportData->periodAverages->toArray(), function ($carry, $item) {
            return $carry + $item;
        }, 0);

        // Calculate the total average of averages
        if ($numberOfPeriods > 0) {
            $workloadReportData->totalAverage = round($averageSum / $numberOfPeriods, 2);
        }

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
     * @param ViewModeEnum $viewMode defines the view mode
     * @param string $workerIdentifier the worker's identifier
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     *
     * @return array the list of workloads matching the criteria defined by the parameters
     */
    private function getWorklogs(ViewModeEnum $viewMode, string $workerIdentifier, \DateTime $dateFrom, \DateTime $dateTo): array
    {
        return match ($viewMode) {
            ViewModeEnum::WORKLOAD => $this->worklogRepository->findWorklogsByWorkerAndDateRange($workerIdentifier, $dateFrom, $dateTo),
            ViewModeEnum::BILLABLE => $this->worklogRepository->findBillableWorklogsByWorkerAndDateRange($workerIdentifier, $dateFrom, $dateTo),
            ViewModeEnum::BILLED => $this->worklogRepository->findBilledWorklogsByWorkerAndDateRange($workerIdentifier, $dateFrom, $dateTo),
        };
    }
}
