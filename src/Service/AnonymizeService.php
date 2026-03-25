<?php

namespace App\Service;

use App\Repository\WorklogRepository;

class AnonymizeService
{
    public function __construct(private readonly WorklogRepository $worklogRepository)
    {
    }

    public function anonymizeWorklogs(\DateTime $anonymizeBefore)
    {
        $this->worklogRepository->anonymizeWorklogs($anonymizeBefore);
    }
}
