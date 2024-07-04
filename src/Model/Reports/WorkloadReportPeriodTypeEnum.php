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
            self::WEEK => $translator->trans('workload_report_period_type_enum.week.label', locale: $locale),
            self::MONTH => $translator->trans('workload_report_period_type_enum.month.label', locale: $locale),
            self::YEAR => $translator->trans('workload_report_period_type_enum.year.label', locale: $locale),
        };
    }
}
