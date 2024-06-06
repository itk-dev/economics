<?php

namespace App\Service;

class DateTimeHelper
{
    public function __construct(
    ) {
    }

    public function getFirstAndLastDateOfWeek(int $weekNumber, int $year = null, string $format = 'Y-m-d H:i:s'): array
    {
        if (!$year) {
            $year = (int) (new \DateTime())->format('Y');
        }

        $firstDateTime = (new \DateTime())->setISODate($year, $weekNumber);
        $firstDateTime->setTime(0, 0, 0);
        $firstDate = $firstDateTime->format($format);

        $lastDateTime = (new \DateTime())->setISODate($year, $weekNumber, 7);
        $lastDateTime->setTime(23, 59, 59);
        $lastDate = $lastDateTime->format($format);

        return ['first' => $firstDate, 'last' => $lastDate];
    }

    public function getFirstAndLastDateOfMonth(int $monthNumber, int $year = null, string $format = 'Y-m-d H:i:s'): array
    {
        if (!$year) {
            $year = (int) (new \DateTime())->format('Y');
        }

        $firstDateTime = (new \DateTime())->setDate($year, $monthNumber, 1);
        $firstDateTime->setTime(0, 0, 0);
        $firstDate = $firstDateTime->format($format);

        $lastDateTime = (new \DateTime())->setDate($year, $monthNumber, 1)->modify('last day of this month');
        $lastDateTime->setTime(23, 59, 59);
        $lastDate = $lastDateTime->format($format);

        return ['first' => $firstDate, 'last' => $lastDate];
    }

    /**
     * Returns an array of the weeks for the current year (ISO 8601).
     *
     * @return array
     */
    public function getWeeksOfYear(int $year = null): array
    {
        if (!$year) {
            $year = (int) (new \DateTime())->format('Y');
        }
        $weekArray = [];
        $start = new \DateTime("{$year}-01-04"); // 4th of Jan always falls in the first week of the year.
        $end = (new \DateTime("{$year}-12-28"))->modify('+1 week'); // 28th of Dec always falls in the last week of the year.
        $interval = new \DateInterval('P1W');

        foreach (new \DatePeriod($start, $interval, $end) as $date) {
            // Year of week.
            $yearOfTheWeek = $date->format('o');

            // If the "year of the week" is greater than the current year, skip this iteration.
            if ($yearOfTheWeek > $year) {
                continue;
            }

            $weekNumber = (int) $date->format('W');
            $weekArray[] = $weekNumber;
        }

        return $weekArray;
    }

    public function getMonthsOfYear(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; ++$i) {
            $monthName = \DateTime::createFromFormat('!m', (string) $i)->format('F');
            $months[$monthName] = $i;
        }

        return $months;
    }

    public function getMonthName(int $monthNumber): string
    {
        return \DateTime::createFromFormat('!m', (string) $monthNumber)->format('F');
    }
}
