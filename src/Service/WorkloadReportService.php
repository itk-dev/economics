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
        $year = (int) (new \DateTime())->format('Y');
        $workers = $this->workerRepository->findAll();
        $periods = $this->getPeriods($viewPeriodType, $year);

        foreach ($periods as $period) {
            $readablePeriod = $this->getReadablePeriod($period, $viewPeriodType);
            $workloadReportData->period->set((string) $period, $readablePeriod);
        }

        foreach ($workers as $worker) {
            $workloadReportWorker = new WorkloadReportWorker();
            $workloadReportWorker->setEmail($worker->getUserIdentifier());
            $workloadReportWorker->setWorkload($worker->getWorkload());
            $currentPeriodReached = false;
            $percentageTotal = 0;
            $count = 0;


            foreach ($periods as $period) {
                $currentPeriodNumeric = $this->getCurrentPeriodNumeric($viewPeriodType);

                if ($period === $currentPeriodNumeric) {
                    $workloadReportData->setCurrentPeriodNumeric($period);
                    $currentPeriodReached = true;
                }

                ['dateFrom' => $dateFrom, 'dateTo' => $dateTo] = $this->getDatesOfPeriod($period, $year, $viewPeriodType);
                $workerIdentifier = $worker->getUserIdentifier();

                if (empty($workerIdentifier)) {
                    throw new \Exception('Worker identifier cannot be empty');
                }

                $worklogs = $this->getWorklogs($viewMode, $workerIdentifier, $dateFrom, $dateTo);
                $loggedHours = 0;
                foreach ($worklogs as $worklog) {
                    $loggedHours += ($worklog->getTimeSpentSeconds() / 60 / 60);
                }

                $workerWorkload = $worker->getWorkload();
                if (!$workerWorkload) {
                    $workerId = $worker->getUserIdentifier();
                    throw new \Exception("Workload of worker: $workerId cannot be unset when generating workload report.");
                }

                $roundedLoggedPercentage = $this->getRoundedLoggedPercentage($loggedHours, $workerWorkload, $viewPeriodType, $dateFrom, $dateTo);

                if (!$currentPeriodReached || $count == 0) {
                    $percentageTotal += $roundedLoggedPercentage;
                    ++$count;
                }

                $workloadReportWorker->loggedPercentage->set($period, $roundedLoggedPercentage);
            }


            $workloadReportWorker->average = round($percentageTotal / $count, 2);

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
    private function getRoundedLoggedPercentage(float $loggedHours, float $workloadWeekBase, PeriodTypeEnum $viewPeriodType, \DateTime $dateFrom, \DateTime $dateTo): float
    {
        // Workload is weekly hours, so for expanded views, it has to be multiplied.
        return match ($viewPeriodType) {
            PeriodTypeEnum::WEEK => round(($loggedHours / $workloadWeekBase) * 100),
            PeriodTypeEnum::MONTH => round(($loggedHours / ($workloadWeekBase * ($this->dateTimeHelper->getWeekdaysBetween($dateFrom, $dateTo) / 5))) * 100),
            PeriodTypeEnum::YEAR => round(($loggedHours / ($workloadWeekBase * ($this->dateTimeHelper->getWeekdaysBetween($dateFrom, $dateTo) / 5))) * 100, 2),
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
        };
    }
}
