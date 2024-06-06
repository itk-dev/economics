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
     * @throws \Exception
     */
    public function getWorkloadReport($viewMode): WorkloadReportData
    {
        // Get period based on viewmode.
        $periods = match ($viewMode) {
            'month' => $this->dateTimeHelper->getMonthsOfYear(),
            'week' => $this->dateTimeHelper->getWeeksOfYear(),
        };

        // Callable to get first and last date of a given period.
        $getDatesOfPeriod = match ($viewMode) {
            'month' => function ($monthNumber) { return $this->dateTimeHelper->getFirstAndLastDateOfMonth($monthNumber); },
            'week' => function ($weekNumber) { return $this->dateTimeHelper->getFirstAndLastDateOfWeek($weekNumber); },
        };

        // Callable to get a readable representation of a given period.
        $getReadablePeriod = match ($viewMode) {
            'month' => fn ($monthNumber) => $this->dateTimeHelper->getMonthName($monthNumber),
            'week' => fn ($period) => $period,
        };

        return $this->getWorkloadData($periods, $getDatesOfPeriod, $getReadablePeriod, $viewMode);
    }

    private function getWorkloadData(array $periods, callable $getDatesOfPeriod, callable $getReadablePeriod, string $viewMode): WorkloadReportData
    {
        $workloadReportData = new WorkloadReportData($viewMode);
        $workers = $this->workerRepository->findAll();

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

                // Add period number to general data for table headers.
                $readablePeriod = $getReadablePeriod($period);
                $workloadReportData->period->add($readablePeriod);

                // Workload is per week, so for a month, it has to be times 4.
                $periodWorkload = ($viewMode == 'month') ? $worker->getWorkload() * 4 : $worker->getWorkload();

                // Get percentage of logged hours based on worker workload.
                $loggedPercentage = round(($loggedHours / $periodWorkload) * 100);

                // Add percentage result to worker for current period.
                $workloadReportWorker->loggedPercentage->set($period, $loggedPercentage);
            }

            $workloadReportData->workers->add($workloadReportWorker);
        }

        return $workloadReportData;
    }

    public function getViewModes(): array
    {
        return [
            'Week' => 'week',
            'Month' => 'month',
        ];
    }
}
