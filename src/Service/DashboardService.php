<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Worklog;
use App\Model\DashboardData;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;

class DashboardService
{
    public function __construct(
        private readonly WorkerRepository $workerRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly DateTimeHelper $dateTimeHelper,
    ) {
    }

    public function getUserDashboard(User $user, ?int $year = null): ?DashboardData
    {
        $userEmail = $user->getEmail();

        if (null === $userEmail) {
            return null;
        }

        $worker = $this->workerRepository->findOneBy(['email' => $userEmail]);

        if (null === $worker) {
            return null;
        }

        $norm = $worker->getWorkload() ?? 0.0;

        if (null == $year) {
            $year = (int) date('Y');
        }

        ['dateFrom' => $yearStart, 'dateTo' => $yearEnd] = $this->dateTimeHelper->getFirstAndLastDateOfYear($year);

        $monthSums = [];
        $weekSums = [];

        $monthNorms = [];

        for ($month = 1; $month <= 12; ++$month) {
            $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

            for ($day = 1; $day <= $daysInMonth; ++$day) {
                $dayDate = \DateTime::createFromFormat('U', (string) mktime(0, 0, 0, $month, $day, $year));

                if ((int) $dayDate->format('N') < 6) {
                    $monthNorms[$month] = ($monthNorms[$month] ?? 0) + 1;
                }
            }

            $monthNorms[$month] = ($monthNorms[$month] ?? 0) / 5 * $norm;
        }

        // Get all worklogs for $year.
        $worklogs = $this->worklogRepository->findWorklogsByWorkerAndDateRange($userEmail, $yearStart, $yearEnd);

        /** @var Worklog $worklog */
        foreach ($worklogs as $worklog) {
            $month = (int) $worklog->getStarted()?->format('n');
            $week = (int) $worklog->getStarted()?->format('W');

            $monthSums[$month] = ($monthSums[$month] ?? 0.0) + $worklog->getTimeSpentSeconds() / 3600;
            $weekSums[$week] = ($weekSums[$week] ?? 0.0) + $worklog->getTimeSpentSeconds() / 3600;
        }

        $yearIsCurrent = $year == (int) date('Y');
        $maxMonth = $yearIsCurrent ? (date('n')) : 12;

        $monthStatuses = [];
        $yearStatus = 0;

        for ($month = 1; $month <= $maxMonth; ++$month) {
            $monthStatuses[$month] = ($monthSums[$month] ?? 0.0) - ($monthNorms[$month] ?? 0.0);

            $yearStatus += $monthStatuses[$month];
        }

        // December 28th is always in the last week of its year.
        $maxWeek = $yearIsCurrent ? (int) (new \DateTime())->format('W') : (int) (new \DateTime('December 28th, '.$year))->format('W');

        $weekStatuses = [];
        for ($week = 1; $week <= $maxWeek; ++$week) {
            $weekStatuses[$week] = ($weekSums[$week] ?? 0.0) - $norm;
        }

        return new DashboardData($yearStatus, $year, $norm, $monthStatuses, $weekStatuses);
    }
}
