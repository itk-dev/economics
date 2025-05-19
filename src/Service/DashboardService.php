<?php

namespace App\Service;

use App\Entity\User;
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

        $weekNorm = $worker->getWorkload() ?? 0.0;
        $dayNorm = $weekNorm / 5;

        $dayNormSeconds = (int) ($dayNorm * 3600);
        $yearNormToDate = 0;

        if (null == $year) {
            $year = (int) date('Y');
        }

        $monthNormsToDate = [];
        $weekNormsToDate = [];

        $today = new \DateTime();
        $today->setTime(23, 59, 59);

        for ($month = 1; $month <= 12; ++$month) {
            $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

            for ($day = 1; $day <= $daysInMonth; ++$day) {
                $dayDate = new \DateTime();
                $dayDate->setDate($year, $month, $day);

                if ($dayDate > $today) {
                    // Exit both for-loops. Including days from the future will skew the norm time calculation.
                    break 2;
                }

                $weekNumber = (int) $dayDate->format('W');

                if ((int) $dayDate->format('N') < 6) {
                    $weekNormsToDate[$weekNumber] = ($weekNormsToDate[$weekNumber] ?? 0) + $dayNormSeconds;
                    $monthNormsToDate[$month] = ($monthNormsToDate[$month] ?? 0) + $dayNormSeconds;
                    $yearNormToDate += $dayNormSeconds;
                }
            }
        }

        ['dateFrom' => $yearStart, 'dateTo' => $yearEnd] = $this->dateTimeHelper->getFirstAndLastDateOfYear($year);
        $today = $this->dateTimeHelper->getToday();

        // Including future dates will skew the norm time calculations. So for the current year "end" is set to "today"
        $end = $yearEnd < $today ? $yearEnd : $today;

        $weekSums = $this->worklogRepository->getTimeSpentByWorkerInWeekRange($userEmail, $yearStart, $end, 'week');
        $monthSums = $this->worklogRepository->getTimeSpentByWorkerInWeekRange($userEmail, $yearStart, $end, 'month');
        $yearSums = $this->worklogRepository->getTimeSpentByWorkerInWeekRange($userEmail, $yearStart, $end, 'year');

        // Since yearSums is now indexed by year, we need to get the current year's data
        $currentYearData = $yearSums[$year] ?? ['totalTimeSpent' => 0];
        $yearStatus = ($currentYearData['totalTimeSpent'] - $yearNormToDate) / 3600;

        $monthStatuses = $this->getMonthsToDate();
        foreach (array_keys($monthNormsToDate) as $month) {
            $totalTimeSpent = isset($monthSums[$month]) ? $monthSums[$month]['totalTimeSpent'] : 0;
            $monthStatuses[$month] = ($totalTimeSpent - $monthNormsToDate[$month]) / 3600;
        }

        $weekStatuses = $this->getWeeksToDate();
        foreach (array_keys($weekStatuses) as $week) {
            $totalTimeSpent = isset($weekSums[$week]) ? $weekSums[$week]['totalTimeSpent'] : 0;
            $weekStatuses[$week] = ($totalTimeSpent - $weekNormsToDate[$week]) / 3600;
        }

        return new DashboardData($yearStatus, $year, $weekNorm, $monthStatuses, $weekStatuses);
    }

    private function getWeeksToDate(): array
    {
        $currentWeek = (int) date('W');
        $weeksToDate = [];
        for ($week = 1; $week <= $currentWeek; ++$week) {
            $weeksToDate[$week] = 0;
        }

        return $weeksToDate;
    }

    private function getMonthsToDate(): array
    {
        $currentMonth = (int) date('m');
        $weeksToDate = [];
        for ($week = 1; $week <= $currentMonth; ++$week) {
            $weeksToDate[$week] = 0;
        }

        return $weeksToDate;
    }
}
