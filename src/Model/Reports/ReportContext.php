<?php

namespace App\Model\Reports;

use App\Entity\WorkerGroup;

final class ReportContext
{
    /**
     * @param string[] $hiddenWorkerEmails
     */
    public function __construct(
        public readonly int $year,
        public readonly ?WorkerGroup $group = null,
        public readonly array $hiddenWorkerEmails = [],
    ) {
    }
}
