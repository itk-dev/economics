<?php

namespace App\Service;

use App\Repository\IssueRepository;
use App\Repository\WorklogRepository;

class CybersecurityReportService
{
    public function __construct(
    )
    {
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getDefaultFromDate(): \DateTime
    {
        $fromDate = new \DateTime();
        $fromDate->modify('first day of this month');

        return $fromDate;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getDefaultToDate(): \DateTime
    {
        $fromDate = new \DateTime();
        $fromDate->modify('last day of this month');

        return $fromDate;
    }
}
