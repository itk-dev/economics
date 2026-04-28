<?php

namespace App\Model\Reports;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum WorkloadReportViewModeEnum: string implements TranslatableInterface
{
    case WORKLOAD = 'workload';
    case BILLABLE = 'billable';
    case BILLED = 'billed';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::WORKLOAD => $translator->trans('workload_report_view_mode_enum.workload.label', locale: $locale),
            self::BILLABLE => $translator->trans('workload_report_view_mode_enum.billable.label', locale: $locale),
            self::BILLED => $translator->trans('workload_report_view_mode_enum.billed.label', locale: $locale),
        };
    }
}
