<?php

namespace App\Service;

class DateTimeHelper
{
    public function __construct(
    ) {
    }


    public function getFirstAndLastDateOfWeek(int $weekNumber, ?int $year = null, ?string $format = 'Y-m-d H:i:s'): array
    {
        if (!$year) {
            $year = (int) (new \DateTime())->format('Y');
        }

        $firstDateTime = (new \DateTime())->setISODate($year, $weekNumber);
        $firstDateTime->setTime(0, 0, 0);
        $firstDate = $firstDateTime->format($format);

        $lastDateTime = (new \DateTime())->setISODate($year, $weekNumber, 7);
        $lastDateTime->setTime(0, 0, 0);
        $lastDate = $lastDateTime->format($format);

        return [$firstDate, $lastDate];
    }
    /**
     * Returns an array of the weeks for the current year (ISO 8601).
     *
     * @return array
     */
    public function getWeeksOfYear(?int $year = null): array
    {
        if (!$year) {
            $year = (int) (new \DateTime())->format('Y');
        }
        $weekArray = [];
        $start = new \DateTime("{$year}-01-04"); // 4th of Jan always falls in the first week of the year.
        $end = (new \DateTime("{$year}-12-28"))->modify('+1 week'); // 28th of Dec always falls in the last week of the year.
        $interval = new \DateInterval('P1W');

        foreach (new \DatePeriod($start, $interval, $end) as $date) {
            $yearOfTheWeek = $date->format('o'); // Year of week.

            // If the "year of the week" is greater than the current year, skip this iteration.
            if ($yearOfTheWeek > $year) {
                continue;
            }

            $weekNumber = (int) $date->format('W');
            $weekArray[] = $weekNumber;
        }

        return $weekArray;
    }
}
