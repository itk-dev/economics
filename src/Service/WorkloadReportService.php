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
        $workloadReportData = new WorkloadReportData($viewPeriodType->value);
        $workers = $this->workerRepository->findAll();
        $periods = $this->getPeriods($viewPeriodType);

        foreach ($periods as $period) {
            // Get period specific readable period representation for table headers.
            $readablePeriod = $this->getReadablePeriod($period, $viewPeriodType);
            $workloadReportData->period->set((string) $period, $readablePeriod);
        }

        foreach ($workers as $worker) {
            $workloadReportWorker = new WorkloadReportWorker();
            $workloadReportWorker->setEmail($worker->getUserIdentifier());
            $workloadReportWorker->setWorkload($worker->getWorkload());

            foreach ($periods as $period) {
                // Add current period match-point (current week-number, month-number etc.)
                $currentPeriodNumeric = $this->getCurrentPeriodNumeric($viewPeriodType);
                if ($period === $currentPeriodNumeric) {
                    $workloadReportData->setCurrentPeriodNumeric($period);
                }
                // Get first and last date in period.
                $firstAndLastDate = $this->getDatesOfPeriod($period, $viewPeriodType);

                // Get all worklogs between the two dates.
                $workerIdentifier = $worker->getUserIdentifier();

                if (empty($workerIdentifier)) {
                    throw new \Exception('Worker identifier cannot be empty');
                }

                $worklogs = $this->getWorkloads($viewMode, $workerIdentifier, $firstAndLastDate);

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
     * @param PeriodTypeEnum $viewPeriodType the view mode ('week' or 'month')
     *
     * @return float the rounded percentage of logged hours
     */
    private function getRoundedLoggedPercentage(float $loggedHours, float $workloadWeekBase, PeriodTypeEnum $viewPeriodType): float
    {
        // Since lunch is paid, subtract this from the actual workload (0.5 * 5)
        $actualWeeklyWorkload = $workloadWeekBase - 2.5;

        // Workload is weekly hours, so for expanded views, it has to be multiplied.
        return match ($viewPeriodType) {
            PeriodTypeEnum::WEEK => round(($loggedHours / $actualWeeklyWorkload) * 100),
            PeriodTypeEnum::MONTH => round(($loggedHours / ($actualWeeklyWorkload * 4)) * 100)
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
            'Week' => PeriodTypeEnum::WEEK->value,
            'Month' => PeriodTypeEnum::MONTH->value,
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
            'Workload %' => ViewModeEnum::WORKLOAD->value,
            'Billable %' => ViewModeEnum::BILLABLE->value,
        ];
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
        };
    }

    /**
     * Retrieves an array of dates for a given period based on the view mode.
     *
     * @param int $period the period for which to retrieve dates
     * @param PeriodTypeEnum $viewMode the view mode to determine the dates of the period
     *
     * @return array an array of dates for the given period
     */
    private function getDatesOfPeriod(int $period, PeriodTypeEnum $viewMode): array
    {
        return match ($viewMode) {
            PeriodTypeEnum::MONTH => $this->dateTimeHelper->getFirstAndLastDateOfMonth($period),
            PeriodTypeEnum::WEEK => $this->dateTimeHelper->getFirstAndLastDateOfWeek($period),
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
            PeriodTypeEnum::WEEK => (string) $period,
        };
    }

    /**
     * Retrieves an array of periods based on the given view mode.
     *
     * @param PeriodTypeEnum $viewMode the view mode to determine the periods
     *
     * @return array an array of periods
     */
    private function getPeriods(PeriodTypeEnum $viewMode): array
    {
        return match ($viewMode) {
            PeriodTypeEnum::MONTH => range(1, 12),
            PeriodTypeEnum::WEEK => $this->dateTimeHelper->getWeeksOfYear(),
        };
    }

    /**
     * Returns workloads based on the provided view mode, worker, and date range.
     *
     * @param ViewModeEnum $viewMode defines the view mode
     * @param string $worker the worker's identifier
     * @param array $firstAndLastDate contains the date range (first and last dates)
     *
     * @return array the list of workloads matching the criteria defined by the parameters
     */
    private function getWorkloads(ViewModeEnum $viewMode, string $worker, array $firstAndLastDate): array
    {
        return match ($viewMode) {
            ViewModeEnum::WORKLOAD => $this->worklogRepository->findWorklogsByWorkerAndDateRange($worker, $firstAndLastDate['first'], $firstAndLastDate['last']),
            ViewModeEnum::BILLABLE => $this->worklogRepository->findBillableWorklogsByWorkerAndDateRange($worker, $firstAndLastDate['first'], $firstAndLastDate['last']),
        };
    }
}
