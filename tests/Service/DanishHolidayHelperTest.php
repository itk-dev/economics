<?php

namespace App\Tests\Service;

use App\Service\DanishHolidayHelper;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

final class DanishHolidayHelperTest extends TestCase
{
    private DanishHolidayHelper $helper;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->helper = DanishHolidayHelper::getInstance();
    }

    public function testHmm()
    {
        $this->assertNotEquals(
            $this->helper->getHolidays(2024),
            $this->helper->getHolidays(2025)
        );
    }

    /**
     * @dataProvider dataEaster
     */
    public function testEaster(int $year, \DateTimeInterface $expected)
    {
        $this->assertSameDate(
            $expected,
            $this->helper->getEaster($year)
        );
    }

    public static function dataEaster(): iterable
    {
        yield '[2023]' => [
            2023,
            new \DateTimeImmutable('2023-04-09'),
        ];

        yield '[2024]' => [
            2024,
            new \DateTimeImmutable('2024-03-31'),
        ];

        yield '[2025]' => [
            2025,
            new \DateTimeImmutable('2025-04-20'),
        ];
    }

    public function testHolidayNames()
    {
        $year = 2024;
        $expected = [
            $this->format('2024-01-01') => 'nytårsdag',
            $this->format('2024-03-24') => 'palmesøndag',
            $this->format('2024-03-28') => 'skærtorsdag',
            $this->format('2024-03-29') => 'langfredag',
            $this->format('2024-03-31') => 'påskedag',
            $this->format('2024-04-01') => '2. påskedag',
            $this->format('2024-04-26') => 'store bededag',
            $this->format('2024-05-09') => 'kristi himmelfartsdag',
            $this->format('2024-05-19') => 'pinsedag',
            $this->format('2024-05-20') => '2. pinsedag',
            $this->format('2024-12-25') => 'juledag',
            $this->format('2024-12-26') => '2. juledag',
        ];
        $actual = $this->helper->getHolidayNames($year);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider dataNextNonHoliday
     */
    public function testNextNonHoliday(\DateTimeInterface $date, ?\DateTimeInterface $expected)
    {
        $actual = $this->helper->getNextNonHoliday($date);
        $this->assertSameDate($expected, $actual);
    }

    public static function dataNextNonHoliday(): iterable
    {
        yield '2024-03-31' => [
            new \DateTimeImmutable('2024-03-31'),
            new \DateTimeImmutable('2024-04-02'),
        ];
    }

    /**
     * @dataProvider dataNextBankDay
     */
    public function testNextBankDay(\DateTimeInterface $date, ?\DateTimeInterface $expected)
    {
        $actual = $this->helper->getNextBankDay($date);
        $this->assertSameDate($expected, $actual);
    }

    public static function dataNextBankDay(): iterable
    {
        yield '2024-03-29' => [
            new \DateTimeImmutable('2024-03-29'),
            new \DateTimeImmutable('2024-04-02'),
        ];

        yield '2024-03-30' => [
            new \DateTimeImmutable('2024-03-30'),
            new \DateTimeImmutable('2024-04-02'),
        ];

        yield '2024-03-31' => [
            new \DateTimeImmutable('2024-03-31'),
            new \DateTimeImmutable('2024-04-02'),
        ];

        yield '2024-06-05' => [
            new \DateTimeImmutable('2024-06-05'),
            new \DateTimeImmutable('2024-06-06'),
        ];
    }

    /**
     * @dataProvider dataNextBankDay30
     */
    public function testNextBankDay30(\DateTimeInterface $date, ?\DateTimeInterface $expected)
    {
        $actual = $this->helper->getNextBankDay($date, 30);
        $this->assertSameDate($expected, $actual);
    }

    public static function dataNextBankDay30(): iterable
    {
        yield '2024-03-29' => [
            new \DateTimeImmutable('2024-02-29'),
            new \DateTimeImmutable('2024-04-02'),
        ];

        yield '2024-03-30' => [
            new \DateTimeImmutable('2024-03-01'),
            new \DateTimeImmutable('2024-04-02'),
        ];

        yield '2024-03-31' => [
            new \DateTimeImmutable('2024-03-02'),
            new \DateTimeImmutable('2024-04-02'),
        ];
    }

    /**
     * @dataProvider dataIsBankHoliday
     */
    public function testIsBankHoliday(\DateTimeInterface $date, bool $expected)
    {
        $actual = $this->helper->isBankHoliday($date);
        $this->assertSame($expected, $actual);
    }

    public static function dataIsBankHoliday(): iterable
    {
        yield '2024-12-31' => [
            new \DateTimeImmutable('2024-12-31'),
            true,
        ];

        yield '2024-01-01' => [
            new \DateTimeImmutable('2024-01-01'),
            true,
        ];

        yield '2024-01-02' => [
            new \DateTimeImmutable('2024-01-02'),
            false,
        ];

        yield '2024-05-10' => [
            new \DateTimeImmutable('2024-05-10'),
            true,
        ];

        yield '2024-12-23' => [
            new \DateTimeImmutable('2024-12-23'),
            false,
        ];

        yield '2024-12-24' => [
            new \DateTimeImmutable('2024-12-24'),
            true,
        ];

        yield '2026-05-15' => [
            new \DateTimeImmutable('2026-05-15'),
            true,
        ];
    }

    private function assertSameDate(\DateTimeInterface $expected, \DateTimeInterface $actual, string $message = '')
    {
        try {
            return $this->assertEquals($expected->getTimestamp(), $actual->getTimestamp(), $message);
        } catch (ExpectationFailedException $exception) {
            throw new ExpectationFailedException(sprintf('Failed asserting that %s matches expected %s.', $expected->format(\DateTimeInterface::ATOM), $actual->format(\DateTimeInterface::ATOM)), $exception->getComparisonFailure(), $exception);
        }
    }

    private function format(string $date): string
    {
        return $this->helper->formatDate(new \DateTimeImmutable($date));
    }
}
