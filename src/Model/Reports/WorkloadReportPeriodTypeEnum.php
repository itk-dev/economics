<?php

namespace App\Model\Reports;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum WorkloadReportPeriodTypeEnum: string implements TranslatableInterface
{
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';

    public function trans(TranslatorInterface $translator, string $locale = null): string
    {
        return match ($this) {
            self::WEEK => $translator->trans('WorkloadReportPeriodTypeEnum.week.label', locale: $locale),
            self::MONTH => $translator->trans('WorkloadReportPeriodTypeEnum.month.label', locale: $locale),
            self::YEAR => $translator->trans('WorkloadReportPeriodTypeEnum.year.label', locale: $locale),
        };
    }
}
