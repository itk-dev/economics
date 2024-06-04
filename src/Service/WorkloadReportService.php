<?php

namespace App\Service;

use App\Exception\EconomicsException;
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
     * @throws EconomicsException
     * @throws \Exception
     */
    public function getWorkloadReport(): WorkloadReportData
    {
        $workloadReportData = new WorkloadReportData();

        $workers = $this->workerRepository->findAll();
        $weeksOfTheYear = $this->dateTimeHelper->getWeeksOfYear();

        foreach ($workers as $worker) {
            $workloadReportWorker = new WorkloadReportWorker();
            $workloadReportWorker->setEmail($worker->getUserIdentifier());
            $workloadReportWorker->setWorkload($worker->getWorkload());

            foreach ($weeksOfTheYear as $week) {
                $firstAndLastDateOfWeek = $this->dateTimeHelper->getFirstAndLastDateOfWeek($week);
                $firstDay = $firstAndLastDateOfWeek[0];
                $lastDay = $firstAndLastDateOfWeek[1];
                $worklogs = $this->worklogRepository->findWorklogsByWorkerAndStartDateRange($worker->getUserIdentifier(), $firstDay, $lastDay);
                $loggedHours = 0;
                foreach ($worklogs as $worklog) {
                    $loggedHours += ($worklog->getTimeSpentSeconds() / 60 / 60);
                }
                $loggedPercentage = round(($loggedHours / $worker->getWorkload()) * 100);
                $workloadReportData->yearWeeks->add($week);
                $workloadReportWorker->hoursLogged->set($week, $loggedPercentage);

            }

            $workloadReportData->workers->add($workloadReportWorker);
        }

        return $workloadReportData;
    }
}
