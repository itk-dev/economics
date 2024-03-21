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

    /**
     * Get holidays indexed by name.
     */
    public function getHolidays(int $year): array
    {
        if (!isset($this->holidays[$year])) {
            $easter = $this->getEaster($year);

            $this->holidays[$year] = [
                'nytårsdag' => $easter->modify('1 January'),
                'palmesøndag' => $easter->modify('-7 days'),
                'skærtorsdag' => $easter->modify('-3 days'),
                'langfredag' => $easter->modify('-2 days'),
                'påskedag' => $easter,
                '2. påskedag' => $easter->modify('+1 day'),
                'store bededag' => $easter->modify('+26 days'),
                'kristi himmelfartsdag' => $easter->modify('+39 days'),
                'pinsedag' => $easter->modify('+49 days'),
                '2. pinsedag' => $easter->modify('+50 days'),
                'juledag' => $easter->modify('25 December'),
                '2. juledag' => $easter->modify('26 December'),
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
            $holidays = $this->getHolidays($year);

            $names = [];
            foreach ($holidays as $name => $date) {
                $names[$this->formatDate($date)] = $name;
            }
            $this->holidayNames[$year] = $names;
        }

        return $this->holidayNames[$year];
    }

    /**
     * Get bank holiday names.
     *
     * @see https://www.nationalbanken.dk/da/vores-arbejde/stabile-priser-pengepolitik-og-dansk-oekonomi/banklukkedage
     */
    public function getBankHolidayNames(int $year): array
    {
        if (!isset($this->bankHolidays[$year])) {
            $this->bankHolidays[2024] = [
                $this->formatDate(new \DateTimeImmutable('2024-05-10')) => 'dagen efter kristi himmelfartsdag',
                $this->formatDate(new \DateTimeImmutable('2024-06-05')) => 'grundlovsdag',
                $this->formatDate(new \DateTimeImmutable('2024-12-31')) => 'nytårsaftensdag',
            ];

            $this->bankHolidays[2025] = [
                $this->formatDate(new \DateTimeImmutable('2025-05-30')) => 'dagen efter kristi himmelfartsdag',
                $this->formatDate(new \DateTimeImmutable('2025-06-05')) => 'grundlovsdag',
                $this->formatDate(new \DateTimeImmutable('2025-12-31')) => 'nytårsaftensdag',
            ];
        }

        return $this->bankHolidays[$year] ?? [];
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
            $this->getHolidayNames((int) $date->format('Y'))
        );
    }

    public function isBankHoliday(\DateTimeInterface $date): bool
    {
        return $this->isHoliday($date)
            || !$this->isWorkday($date)
            || array_key_exists(
                $this->formatDate($date),
                $this->getBankHolidayNames((int) $date->format('Y'))
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

    public function getNextBankDay(\DateTimeInterface $date, int $daysFromNow = 0): \DateTimeInterface
    {
        $d = \DateTime::createFromInterface($date);
        if (0 !== $daysFromNow) {
            $d->modify($daysFromNow.' days');
        }
        while ($this->isHoliday($d) || !$this->isWorkday($d) || $this->isBankHoliday($d)
        ) {
            $d->modify('+1 day');
        }

        return $d;
    }

    public function formatDate(\DateTimeInterface $date): string
    {
        return $date->format(\DateTimeInterface::ATOM);
    }
}
