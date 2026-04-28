<?php

namespace App\Service;

class DateTimeHelper
{
    public function __construct(
    ) {
    }

    /**
     * Retrieves the first and last date of a given week number in a year.
     *
     * @param int $weekNumber the week number for which to retrieve the dates
     * @param int $year       the year for which to retrieve the dates
     *
     * @return array an array containing the first and last date of the week
     */
    public function getFirstAndLastDateOfWeek(int $weekNumber, int $year): array
    {
        $dateFrom = (new \DateTime())->setISODate($year, $weekNumber, 1);
        $dateFrom->setTime(0, 0, 0);

        $dateTo = (new \DateTime())->setISODate($year, $weekNumber, 7);
        $dateTo->setTime(23, 59, 59);

        return ['dateFrom' => $dateFrom, 'dateTo' => $dateTo];
    }

    /**
     * Returns the first and last date of the specified month and year.
     *
     * @param int $monthNumber the month number (1-12)
     * @param int $year        the year
     *
     * @return array an array containing the first and last date of the specified month and year
     */
    public function getFirstAndLastDateOfMonth(int $monthNumber, int $year): array
    {
        $dateFrom = (new \DateTime())->setDate($year, $monthNumber, 1);
        $dateFrom->setTime(0, 0, 0);

        $dateTo = (new \DateTime())->setDate($year, $monthNumber, 1)->modify('last day of this month');
        $dateTo->setTime(23, 59, 59);

        return ['dateFrom' => $dateFrom, 'dateTo' => $dateTo];
    }

    /**
     * Calculate the number of weekdays (Mon-Fri) between two dates in an associative array.
     *
     * @throws \Exception
     */
    public function getWeekdaysBetween(\DateTime $dateFrom, \DateTime $dateTo): int
    {
        $weekdays = 0;
        // Formatted 'N' Monday is 1, Sunday is 7. So, 1-5 will be weekdays
        while ($dateFrom <= $dateTo) {
            if ($dateFrom->format('N') < 6) {
                ++$weekdays;
            }
            $dateFrom->modify('+1 day');
        }

        return $weekdays;
    }

    /**
     * Retrieves an array of week numbers for a given year.
     *
     * @param int $year the year for which to retrieve the week numbers
     *
     * @return array an array of week numbers
     */
    public function getWeeksOfYear(int $year): array
    {
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

    /**
     * Retrieves the name of the month for a given month number.
     *
     * @param int $monthNumber the month number for which to retrieve the month name
     *
     * @return string the name of the month
     */
    public function getMonthName(int $monthNumber): string
    {
        return \DateTime::createFromFormat('!m', (string) $monthNumber)->format('F');
    }

    /**
     * Returns the first and last date of the specified year.
     *
     * @param int $year the year
     *
     * @return array an array containing the first and last date of the specified year
     */
    public function getFirstAndLastDateOfYear(int $year): array
    {
        $dateFrom = (new \DateTime())->setDate($year, 1, 1);
        $dateFrom->setTime(0, 0, 0);

        $dateTo = (new \DateTime())->setDate($year, 12, 31);
        $dateTo->setTime(23, 59, 59);

        return ['dateFrom' => $dateFrom, 'dateTo' => $dateTo];
    }

    /**
     * Returns the first and last date of the specified quarter in a year.
     *
     * @param int $year    the year
     * @param int $quarter the quarter (1-4)
     *
     * @return array an array containing the first and last date of the specified quarter
     */
    public function getFirstAndLastDateOfQuarter(int $year, int $quarter): array
    {
        // Get the first and last month of the quarter.
        $firstMonth = match ($quarter) {
            1 => 1,
            2 => 4,
            3 => 7,
            4 => 10,
        };
        $lastMonth = $firstMonth + 2;

        $dateFrom = (new \DateTime())->setDate($year, $firstMonth, 1);
        $dateFrom->setTime(0, 0);

        try {
            $dateTo = (new \DateTime())->setDate($year, $lastMonth, 1)->modify('last day of this month');
        } catch (\DateMalformedStringException $e) {
            throw new \RuntimeException('Failed to parse date string', 0, $e);
        }

        $dateTo->setTime(23, 59, 59);

        return ['dateFrom' => $dateFrom, 'dateTo' => $dateTo];
    }

    /**
     * Retrieves the current date with the time set to 23:59:59.
     *
     * @return \DateTime the current date with the time set
     */
    public function getToday(): \DateTime
    {
        $today = new \DateTime();
        $today->setTime(23, 59, 59);

        return $today;
    }
}
