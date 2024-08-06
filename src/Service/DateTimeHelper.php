<?php

namespace App\Service;

class DateTimeHelper
{
    public function __construct(
    ) {
    }

    /**
     * Returns the first and last date of a given week in a year (ISO 8601).
     *
     * @param int $weekNumber the week number
     * @param int $year the year
     * @param string $format The date format to be returned. Defaults to 'Y-m-d H:i:s'.
     *
     * @return array an array with the first and last date of the week
     */
    public function getFirstAndLastDateOfWeek(int $weekNumber, int $year, string $format = 'Y-m-d H:i:s'): array
    {
        $firstDateTime = (new \DateTime())->setISODate($year, $weekNumber, 1);
        $firstDateTime->setTime(0, 0, 0);
        $firstDate = $firstDateTime->format($format);

        $lastDateTime = (new \DateTime())->setISODate($year, $weekNumber, 7);
        $lastDateTime->setTime(23, 59, 59);
        $lastDate = $lastDateTime->format($format);

        return ['first' => $firstDate, 'last' => $lastDate];
    }

    /**
     * Returns the first and last date of the specified month and year.
     *
     * @param int $monthNumber the month number (1-12)
     * @param int $year the year
     * @param string $format the format to use for the returned dates
     *
     * @return array an array containing the first and last date of the specified month and year
     */
    public function getFirstAndLastDateOfMonth(int $monthNumber, int $year, string $format = 'Y-m-d H:i:s'): array
    {
        $firstDateTime = (new \DateTime())->setDate($year, $monthNumber, 1);
        $firstDateTime->setTime(0, 0, 0);
        $firstDate = $firstDateTime->format($format);

        $lastDateTime = (new \DateTime())->setDate($year, $monthNumber, 1)->modify('last day of this month');
        $lastDateTime->setTime(23, 59, 59);
        $lastDate = $lastDateTime->format($format);

        return ['first' => $firstDate, 'last' => $lastDate];
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
     * @param string $format the format to use for the returned dates
     *
     * @return array an array containing the first and last date of the specified year
     */
    public function getFirstAndLastDateOfYear(int $year, string $format = 'Y-m-d H:i:s'): array
    {
        $firstDateTime = (new \DateTime())->setDate($year, 1, 1);
        $firstDateTime->setTime(0, 0, 0);
        $firstDate = $firstDateTime->format($format);

        $lastDateTime = (new \DateTime())->setDate($year, 12, 31);
        $lastDateTime->setTime(23, 59, 59);
        $lastDate = $lastDateTime->format($format);

        return ['first' => $firstDate, 'last' => $lastDate];
    }

    /**
     * Converts the date to Europe/Copenhagen timezone.
     *
     * @param \DateTime|string $datetime
     *
     * @return \DateTime
     */
    public function convertToLocalTimezone(\DateTime|string $datetime): \DateTime
    {
        if (is_string($datetime)) {
            $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime, new \DateTimeZone('UTC'));
        }

        $datetime->setTimezone(new \DateTimeZone('UTC'));

        if ($datetime instanceof \DateTime) {
            $datetime->setTimezone(new \DateTimeZone('Europe/Copenhagen'));
        }

        return $datetime;
    }
}
