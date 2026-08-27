<?php

namespace App\Service;

use App\Repository\WorklogRepository;

class AnonymizeService
{
    public function __construct(private readonly WorklogRepository $worklogRepository)
    {
    }

    public function anonymizeWorklogs(\DateTimeInterface $anonymizeBefore): int
    {
        return $this->worklogRepository->anonymizeWorklogs($anonymizeBefore);
    }
}
