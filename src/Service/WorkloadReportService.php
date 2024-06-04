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
    ) {
    }

    /**
     * @throws EconomicsException
     */
    public function getWorkloadReport(): WorkloadReportData
    {
        $workloadReportData = new WorkloadReportData();

        $workers = $this->workerRepository->findAll();

        foreach ($workers as $worker) {
            $workloadReportWorker = new WorkloadReportWorker();
            $workloadReportWorker->setEmail($worker->getUserIdentifier());
            $workloadReportWorker->setWorkload($worker->getWorkload());
            $worklogs = $this->worklogRepository->findBy(['worker' => $worker->getUserIdentifier()]);
            $loggedHours = 0;
            foreach ($worklogs as $worklog) {
                $loggedHours += ($worklog->getTimeSpentSeconds() / 60 / 60);
            }

            $workloadReportWorker->setHoursLogged($loggedHours);
            $workloadReportData->workers->add($workloadReportWorker);
        }

        return $workloadReportData;
    }
}
