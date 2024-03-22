<?php

namespace App\Service;

final class DanishHolidayHelper
{
    /**
     * ISO 8601 numeric representation of the day of the week.
     *
     * @see https://en.wikipedia.org/wiki/ISO_8601#Week_dates
     */
    public const SATURDAY = 6;
    public const SUNDAY = 7;

    /**
     * @var DanishHolidayHelper
     */
    private static $instance;

    public static function getInstance(): self
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct(
        private readonly array $nonWorkdays = [self::SATURDAY, self::SUNDAY])
    {
    }

    private array $holidays = [];
    private array $holidayNames = [];
    private array $bankHolidays = [];
    private array $bankHolidayNames = [];

    /**
     * Get holidays indexed by name.
     *
     * @return array<string, \DateTimeImmutable>
     */
    public function getHolidays(int $year): array
    {
        if (!isset($this->holidays[$year])) {
            $easter = $this->getEaster($year);

            $this->holidays[$year] = [
                'nytårsdag' => $this->createDate($year, 01, 01),
                'palmesøndag' => $easter->modify('-7 days'),
                'skærtorsdag' => $easter->modify('-3 days'),
                'langfredag' => $easter->modify('-2 days'),
                'påskedag' => $easter,
                '2. påskedag' => $easter->modify('+1 day'),
                'store bededag' => $easter->modify('+26 days'),
                'kristi himmelfartsdag' => $easter->modify('+39 days'),
                'pinsedag' => $easter->modify('+49 days'),
                '2. pinsedag' => $easter->modify('+50 days'),
                'juledag' => $this->createDate($year, 12, 25),
                '2. juledag' => $this->createDate($year, 12, 26),
            ];
        }

        return $this->holidays[$year];
    }

    /**
     * Get holiday names indexed by formatted date.
     */
    public function getHolidayNames(int $year): array
    {
        if (!isset($this->holidayNames[$year])) {
            $this->holidayNames[$year] = $this->buildNames($this->getHolidays($year));
        }

        return $this->holidayNames[$year];
    }

    /**
     * Get bank holidays.
     *
     * @see https://www.nationalbanken.dk/da/vores-arbejde/stabile-priser-pengepolitik-og-dansk-oekonomi/banklukkedage
     */
    public function getBankHolidays(int $year): array
    {
        if (!isset($this->bankHolidays[$year])) {
            $holidays = $this->getHolidays($year);
            $ascensionDay = $holidays['kristi himmelfartsdag'];
            $this->bankHolidays[$year] = [
                'dagen efter kristi himmelfartsdag' => $ascensionDay->modify('+1 day'),
                'grundlovsdag' => $this->createDate($year, 06, 05),
                'juleaftensdag' => $this->createDate($year, 12, 24),
                'nytårsaftensdag' => $this->createDate($year, 12, 31),
            ];
        }

        return $this->bankHolidays[$year];
    }

    public function getBankHolidayNames(int $year): array
    {
        if (!isset($this->bankHolidayNames[$year])) {
            $this->bankHolidayNames[$year] = $this->buildNames($this->getBankHolidays($year));
        }

        return $this->bankHolidayNames[$year];
    }

    public function getEaster(int $year): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())
            ->setTimestamp(easter_date($year));
    }

    public function isHoliday(\DateTimeInterface $date): bool
    {
        return array_key_exists(
            $this->formatDate($date),
            $this->getHolidayNames($this->getYear($date))
        );
    }

    public function isBankHoliday(\DateTimeInterface $date): bool
    {
        return $this->isHoliday($date)
            || !$this->isWorkday($date)
            || array_key_exists(
                $this->formatDate($date),
                $this->getBankHolidayNames($this->getYear($date))
            );
    }

    public function isWorkday(\DateTimeInterface $date): bool
    {
        return !in_array((int) $date->format('N'), $this->nonWorkdays, true);
    }

    public function getNextNonHoliday(\DateTimeInterface $date): \DateTimeInterface
    {
        $d = \DateTime::createFromInterface($date);
        while ($this->isHoliday($d)) {
            $d->modify('+1 day');
        }

        return $d;
    }

    public function getNextBankDay(\DateTimeInterface $date, int $daysFromNow = 0): \DateTimeImmutable
    {
        $d = \DateTime::createFromInterface($date);
        if (0 !== $daysFromNow) {
            $d->modify($daysFromNow.' days');
        }
        while ($this->isHoliday($d) || !$this->isWorkday($d) || $this->isBankHoliday($d)
        ) {
            $d->modify('+1 day');
        }

        return \DateTimeImmutable::createFromInterface($d);
    }

    public function formatDate(\DateTimeInterface $date): string
    {
        return $date->format(\DateTimeInterface::ATOM);
    }

    private function createDate(int $year, int $month, int $day): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())
            ->setDate($year, $month, $day)
            ->setTime(0, 0);
    }

    private function getYear(\DateTimeInterface $date): int
    {
        return (int) $date->format('Y');
    }

    private function buildNames(array $days): array
    {
        $names = [];
        foreach ($days as $name => $date) {
            $names[$this->formatDate($date)] = $name;
        }

        return $names;
    }
}
