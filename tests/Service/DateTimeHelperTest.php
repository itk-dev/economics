<?php

namespace App\Tests\Service;

use App\Service\DateTimeHelper;
use PHPUnit\Framework\TestCase;

class DateTimeHelperTest extends TestCase
{
    /**
     * @var DateTimeHelper
     */
    protected DateTimeHelper $dateTimeHelper;

    public function setUp(): void
    {
        parent::setUp();

        $this->dateTimeHelper = new DateTimeHelper();
    }

    /**
     * @dataProvider weekYearProvider
     */
    public function testGetFirstAndLastDateOfWeek(int $weekNumber, int $year, array $expected): void
    {
        $result = $this->dateTimeHelper->getFirstAndLastDateOfWeek($weekNumber, $year);
        $this->assertEquals($expected, $result);
    }

    public static function weekYearProvider(): array
    {
        return [
            [
                'weekNumber' => 1,
                'year' => 2024,
                'expected' => [
                    'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                    'dateTo' => new \DateTime('2024-01-07 23:59:59'),
                ],
            ],
            [
                'weekNumber' => 52,
                'year' => 2024,
                'expected' => [
                    'dateFrom' => new \DateTime('2024-12-23 00:00:00'),
                    'dateTo' => new \DateTime('2024-12-29 23:59:59'),
                ],
            ],
            [
                'weekNumber' => 30,
                'year' => 2023,
                'expected' => [
                    'dateFrom' => new \DateTime('2023-07-24 00:00:00'),
                    'dateTo' => new \DateTime('2023-07-30 23:59:59'),
                ],
            ],
            [
                'weekNumber' => 1,
                'year' => 2025,
                'expected' => [
                    'dateFrom' => new \DateTime('2024-12-30 00:00:00'),
                    'dateTo' => new \DateTime('2025-01-05 23:59:59'),
                ],
            ],
        ];
    }

    /**
     * @dataProvider monthYearProvider
     */
    public function testGetFirstAndLastDateOfMonth(int $monthNumber, int $year, array $expected): void
    {
        $result = $this->dateTimeHelper->getFirstAndLastDateOfMonth($monthNumber, $year);
        $this->assertEquals($expected, $result);
    }

    public static function monthYearProvider(): array
    {
        return [
            [
                'monthNumber' => 1,
                'year' => 2024,
                'expected' => [
                    'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                    'dateTo' => new \DateTime('2024-01-31 23:59:59'),
                ],
            ],
            [
                'monthNumber' => 12,
                'year' => 2024,
                'expected' => [
                    'dateFrom' => new \DateTime('2024-12-01 00:00:00'),
                    'dateTo' => new \DateTime('2024-12-31 23:59:59'),
                ],
            ],
            [
                'monthNumber' => 2,
                'year' => 2023,
                'expected' => [
                    'dateFrom' => new \DateTime('2023-02-01 00:00:00'),
                    'dateTo' => new \DateTime('2023-02-28 23:59:59'),
                ],
            ],
            [
                'monthNumber' => 2,
                'year' => 2024,
                'expected' => [
                    'dateFrom' => new \DateTime('2024-02-01 00:00:00'),
                    'dateTo' => new \DateTime('2024-02-29 23:59:59'),
                ],
            ],
        ];
    }

    /**
     * @dataProvider weeksOfYearProvider
     */
    public function testGetWeeksOfYear(int $year, array $expected): void
    {
        $result = $this->dateTimeHelper->getWeeksOfYear($year);
        $this->assertEquals($expected, $result);
    }

    public static function weeksOfYearProvider(): array
    {
        return [
            [
                'year' => 2024,
                'expected' => range(1, 52),
            ],
            [
                'year' => 2025,
                'expected' => range(1, 52),
            ],
            [
                'year' => 2026,
                'expected' => range(1, 53),
            ],
        ];
    }

    /**
     * @dataProvider monthNameDataProvider
     */
    public function testGetMonthName(int $monthNumber, string $expectedMonthName): void
    {
        $monthName = $this->dateTimeHelper->getMonthName($monthNumber);
        $this->assertEquals($expectedMonthName, $monthName);
    }

    public static function monthNameDataProvider(): array
    {
        return [
            [1, 'January'],
            [2, 'February'],
            [3, 'March'],
            [4, 'April'],
            [5, 'May'],
            [6, 'June'],
            [7, 'July'],
            [8, 'August'],
            [9, 'September'],
            [10, 'October'],
            [11, 'November'],
            [12, 'December'],
        ];
    }

    /**
     * @dataProvider yearProvider
     */
    public function testGetFirstAndLastDateOfYear(int $year, array $expected): void
    {
        $result = $this->dateTimeHelper->getFirstAndLastDateOfYear($year);
        $this->assertEquals($expected, $result);
    }

    public static function yearProvider(): array
    {
        return [
            [
                'year' => 2024,
                'expected' => [
                    'dateFrom' => new \DateTime('2024-01-01 00:00:00'),
                    'dateTo' => new \DateTime('2024-12-31 23:59:59'),
                ],
            ],
            [
                'year' => 2025,
                'expected' => [
                    'dateFrom' => new \DateTime('2025-01-01 00:00:00'),
                    'dateTo' => new \DateTime('2025-12-31 23:59:59'),
                ],
            ],
            [
                'year' => 2026,
                'expected' => [
                    'dateFrom' => new \DateTime('2026-01-01 00:00:00'),
                    'dateTo' => new \DateTime('2026-12-31 23:59:59'),
                ],
            ],
        ];
    }
}
