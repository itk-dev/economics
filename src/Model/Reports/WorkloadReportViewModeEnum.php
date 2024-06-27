<?php

namespace App\Model\Reports;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum WorkloadReportViewModeEnum: string implements TranslatableInterface
{
    case WORKLOAD = 'workload_percentage_logged';
    case BILLABLE = 'billable_percentage_logged';

    public function trans(TranslatorInterface $translator, string $locale = null): string
    {
        return match ($this) {
            self::WORKLOAD => $translator->trans('WorkloadReportViewModeEnum.workload.label', locale: $locale),
            self::BILLABLE => $translator->trans('WorkloadReportViewModeEnum.billable.label', locale: $locale),
        };
    }
}
