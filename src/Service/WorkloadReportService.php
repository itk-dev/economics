<?php

/**
 * @throws \Exception
 */

namespace App\Service;

use /*
 * Class WorkloadReportData
 *
 * This class retrieves workload data for generating reports.
 */
App\Model\Reports\WorkloadReportData;
use /*
 * @var Connection
 */
App\Model\Reports\WorkloadReportWorker;
use /*
 * @method Worker|null find($id, $lockMode = null, $lockVersion = null)
 * @method Worker|null findOneBy(array $criteria, array $orderBy = null)
 * @method Worker[]    findAll()
 * @method Worker[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
App\Repository\WorkerRepository;
use /*
 * @method Worklog|null find($id, $lockMode = null, $lockVersion = null)
 * @method Worklog|null findOneBy(array $criteria, array $orderBy = null)
 * @method Worklog[]    findAll()
 * @method Worklog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
App\Repository\WorklogRepository;

class WorkloadReportService
{
    public function __construct(
        private readonly WorkerRepository $workerRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly DateTimeHelper $dateTimeHelper,
    ) {
    }

    /**
     * Get the workload report based on the specified view mode.
     *
     * @param string $viewMode The view mode (default: 'week')
     *
     * @return WorkloadReportData The workload report data
     *
     * @throws \Exception When an unexpected value for viewMode is provided
     */
    public function getWorkloadReport(string $viewMode = 'week'): WorkloadReportData
    {
        // Get period based on viewmode.
        $periods = match ($viewMode) {
            'month' => $this->dateTimeHelper->getMonthsOfYear(),
            'week' => $this->dateTimeHelper->getWeeksOfYear(),
            default => throw new \Exception("Unexpected value for viewMode: $viewMode in periods match"),
        };

        // Callable to get first and last date of a given period.
        $getDatesOfPeriod = match ($viewMode) {
            'month' => function ($monthNumber) { return $this->dateTimeHelper->getFirstAndLastDateOfMonth($monthNumber); },
            'week' => function ($weekNumber) { return $this->dateTimeHelper->getFirstAndLastDateOfWeek($weekNumber); },
            default => throw new \Exception("Unexpected value for viewMode: $viewMode in getDatesOfPeriod match"),
        };

        // Callable to get a readable representation of a given period.
        $getReadablePeriod = match ($viewMode) {
            'month' => fn ($monthNumber) => $this->dateTimeHelper->getMonthName($monthNumber),
            'week' => fn ($period) => $period,
            default => throw new \Exception("Unexpected value for viewMode: $viewMode in getReadablePeriod match"),
        };

        return $this->getWorkloadData($periods, $getDatesOfPeriod, $getReadablePeriod, $viewMode);
    }

    /**
     * Get the workload data based on the specified periods, date calculation method, readable period representation method
     * and view mode.
     *
     * @param array $periods The list of periods
     * @param callable $getDatesOfPeriod The callable to get the first and last date of a given period
     * @param callable $getReadablePeriod The callable to get a readable representation of a given period
     * @param string $viewMode The view mode
     *
     * @return WorkloadReportData The workload report data
     *
     * @throws \Exception When the calculated roundedLoggedPercentage is null
     */
    private function getWorkloadData(array $periods, callable $getDatesOfPeriod, callable $getReadablePeriod, string $viewMode): WorkloadReportData
    {
        $workloadReportData = new WorkloadReportData($viewMode);
        $workers = $this->workerRepository->findAll();

        foreach ($periods as $period) {
            // Get period specific readable period representation for table headers.
            $readablePeriod = $getReadablePeriod($period);
            $workloadReportData->period->add($readablePeriod);
        }
        foreach ($workers as $worker) {
            $workloadReportWorker = new WorkloadReportWorker();
            $workloadReportWorker->setEmail($worker->getUserIdentifier());
            $workloadReportWorker->setWorkload($worker->getWorkload());

            foreach ($periods as $period) {
                // Get first and last date in period.
                $firstAndLastDate = $getDatesOfPeriod($period);

                // Get all worklogs between the two dates.
                $worklogs = $this->worklogRepository->findWorklogsByWorkerAndDateRange($worker->getUserIdentifier(), $firstAndLastDate['first'], $firstAndLastDate['last']);

                // Tally up logged hours in gathered worklogs for current period.
                $loggedHours = 0;
                foreach ($worklogs as $worklog) {
                    $loggedHours += ($worklog->getTimeSpentSeconds() / 60 / 60);
                }

                if (!$worker->getWorkload()) {
                    $workerId = $worker->getUserIdentifier();
                    throw new \Exception("Workload of worker: $workerId cannot be unset when generating workload report.");
                }

                // Get total logged percentage based on weekly workload.
                $roundedLoggedPercentage = $this->getRoundedLoggedPercentage($loggedHours, $worker->getWorkload(), $viewMode);

                if (!$roundedLoggedPercentage) {
                    throw new \Exception("Value of calculated roundedLoggedPercentage: $roundedLoggedPercentage cannot be null");
                }

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
     * @param string $viewMode the view mode ('week' or 'month')
     *
     * @return float the rounded percentage of logged hours
     */
    private function getRoundedLoggedPercentage(float $loggedHours, float $workloadWeekBase, string $viewMode): float
    {
        // Since lunch is paid, subtract this from the actual workload (0.5 * 5)
        $actualWeeklyWorkload = $workloadWeekBase - 2.5;

        // Workload is weekly hours, so for expanded views, it has to be multiplied.
        return match ($viewMode) {
            'week' => round(($loggedHours / $actualWeeklyWorkload) * 100),
            'month' => round(($loggedHours / ($actualWeeklyWorkload * 4)) * 100)
        };
    }

    /**
     * Retrieves the available view modes.
     *
     * @return array the array containing the available view modes
     */
    public function getViewModes(): array
    {
        return [
            'Week' => 'week',
            'Month' => 'month',
        ];
    }
}
