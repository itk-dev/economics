<?php

namespace App\Service;

use App\Entity\Worker;
use App\Model\Reports\ReportContext;
use App\Repository\WorkerRepository;

class WorkerFilter
{
    public function __construct(
        private readonly WorkerRepository $workerRepository,
    ) {
    }

    /**
     * Returns the workers that should appear in a report given the context's
     * group filter and the user's hidden-worker list.
     *
     * @return Worker[]
     */
    public function findWorkers(ReportContext $context): array
    {
        $workers = null !== $context->group
            ? $this->workerRepository->findIncludedInReportsByGroup($context->group)
            : $this->workerRepository->findAllIncludedInReports();

        if (empty($context->hiddenWorkerEmails)) {
            return $workers;
        }

        return array_values(array_filter(
            $workers,
            fn (Worker $worker) => !in_array((string) $worker->getEmail(), $context->hiddenWorkerEmails, true),
        ));
    }
}
