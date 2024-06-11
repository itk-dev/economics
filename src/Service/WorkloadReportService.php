<?php

namespace App\Service;

use App\Model\Reports\WorkloadReportData;
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
     * @param string $viewPeriodType The view period type (default: 'week')
     *
     * @param string $viewMode the view mode to generate the report for
     *
     * @return WorkloadReportData the workload report data
     *
     * @throws \Exception when the workload of a worker cannot be unset
     */
    public function getWorkloadReport(string $viewPeriodType = 'week', string $viewMode = 'workload_percentage_logged'): WorkloadReportData
    {
        $workloadReportData = new WorkloadReportData($viewPeriodType);
        $workers = $this->workerRepository->findAll();
        $periods = $this->getPeriods($viewMode);

        foreach ($periods as $period) {
            // Get period specific readable period representation for table headers.
            $readablePeriod = $this->getReadablePeriod($period, $viewMode);
            $workloadReportData->period->set((string) $period, $readablePeriod);
        }

        foreach ($workers as $worker) {
            $workloadReportWorker = new WorkloadReportWorker();
            $workloadReportWorker->setEmail($worker->getUserIdentifier());
            $workloadReportWorker->setWorkload($worker->getWorkload());

            foreach ($periods as $period) {
                // Add current period match-point (current week-number, month-number etc.)
                $currentPeriodNumeric = $this->getCurrentPeriodNumeric($viewMode);
                if ($period === $currentPeriodNumeric) {
                    $workloadReportData->setCurrentPeriodNumeric($period);
                }
                // Get first and last date in period.
                $firstAndLastDate = $this->getDatesOfPeriod($period, $viewMode);

                // Get all worklogs between the two dates.
                $worklogs = $this->worklogRepository->findWorklogsByWorkerAndDateRange($worker->getUserIdentifier(), $firstAndLastDate['first'], $firstAndLastDate['last']);

                // Tally up logged hours in gathered worklogs for current period.
                $loggedHours = 0;
                foreach ($worklogs as $worklog) {
                    $loggedHours += ($worklog->getTimeSpentSeconds() / 60 / 60);
                }

                $workerWorkload = $worker->getWorkload();

                if (!$workerWorkload) {
                    $workerId = $worker->getUserIdentifier();
                    throw new \Exception("Workload of worker: $workerId cannot be unset when generating workload report.");
                }

                // Get total logged percentage based on weekly workload.
                $roundedLoggedPercentage = $this->getRoundedLoggedPercentage($loggedHours, $workerWorkload, $viewPeriodType);

                // Add percentage result to worker for current period.
                $workloadReportWorker->loggedPercentage->set($period, $roundedLoggedPercentage);
            }

            $workloadReportData->workers->add($workloadReportWorker);
        }

        return $workloadReportData;
    }

    /**
     * Calculates the rounded percentage of logged hours based on the workload and view mode.
     *
     * @param float $loggedHours the number of logged hours
     * @param float $workloadWeekBase the base weekly workload (including lunch hours)
     * @param string $viewPeriodType the view mode ('week' or 'month')
     *
     * @return float the rounded percentage of logged hours
     */
    private function getRoundedLoggedPercentage(float $loggedHours, float $workloadWeekBase, string $viewPeriodType): float
    {
        // Since lunch is paid, subtract this from the actual workload (0.5 * 5)
        $actualWeeklyWorkload = $workloadWeekBase - 2.5;

        // Workload is weekly hours, so for expanded views, it has to be multiplied.
        return match ($viewPeriodType) {
            'week' => round(($loggedHours / $actualWeeklyWorkload) * 100),
            'month' => round(($loggedHours / ($actualWeeklyWorkload * 4)) * 100)
        };
    }


    /**
     * Retrieves the available view period types.
     *
     * @return array the array containing the available view period types
     */
    public function getViewPeriodTypes(): array
    {
        return [
            'Week' => 'week',
            'Month' => 'month',
        ];
    }

    /**
     * Retrieves the available view modes.
     *
     * @return array the array containing the available view modes
     */
    public function getViewModes(): array
    {
        return [
            'Workload %' => 'workload_percentage_logged',
            'Billable %' => 'billable_percentage_logged',
        ];
    }

    /**
     * Retrieves the current period as a numeric value based on the given view mode.
     *
     * @param string $viewMode the view mode to determine the current period
     *
     * @return int the current period as a numeric value
     *
     * @throws \Exception when an unexpected value for viewMode is provided
     */
    private function getCurrentPeriodNumeric(string $viewMode): int
    {
        return match ($viewMode) {
            'month' => (int) (new \DateTime())->format('n'),
            'week' => (int) (new \DateTime())->format('W'),
            default => throw new \Exception("Unexpected value for viewMode: $viewMode in getCurrentPeriodNumeric match"),
        };
    }

    /**
     * Retrieves an array of dates for a given period based on the view mode.
     *
     * @param int $period the period for which to retrieve dates
     * @param string $viewMode the view mode to determine the dates of the period
     *
     * @return array an array of dates for the given period
     *
     * @throws \Exception when an unexpected value for viewMode is provided
     */
    private function getDatesOfPeriod(int $period, string $viewMode): array
    {
        $periodDates = match ($viewMode) {
            'month' => function ($monthNumber) { return $this->dateTimeHelper->getFirstAndLastDateOfMonth($monthNumber); },
            'week' => function ($weekNumber) { return $this->dateTimeHelper->getFirstAndLastDateOfWeek($weekNumber); },
            default => throw new \Exception("Unexpected value for viewMode: $viewMode in getDatesOfPeriod match"),
        };

        return $periodDates($period);
    }

    /**
     * Retrieves the readable period based on the given period and view mode.
     *
     * @param int $period the period to be made readable
     * @param string $viewMode the view mode to determine the format of the readable period
     *
     * @return string the readable period
     *
     * @throws \Exception when an unexpected value for viewMode is provided
     */
    private function getReadablePeriod(int $period, string $viewMode): string
    {
        $readablePeriod = match ($viewMode) {
            'month' => fn ($monthNumber) => $this->dateTimeHelper->getMonthName($monthNumber),
            'week' => fn ($weekNumber) => (string) $weekNumber,
            default => throw new \Exception("Unexpected value for viewMode: $viewMode in getReadablePeriod match"),
        };

        return $readablePeriod($period);
    }

    /**
     * Retrieves an array of periods based on the given view mode.
     *
     * @param string $viewMode the view mode to determine the periods
     *
     * @return array an array of periods
     *
     * @throws \Exception when an unexpected value for viewMode is provided
     */
    private function getPeriods(string $viewMode): array
    {
        // Get period based on viewmode.
        return match ($viewMode) {
            'month' => range(1, 12),
            'week' => $this->dateTimeHelper->getWeeksOfYear(),
            default => throw new \Exception("Unexpected value for viewMode: $viewMode in periods match"),
        };
    }
}
