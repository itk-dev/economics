<?php

namespace App\Model\Reports;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum InvoicingRateReportViewModeEnum: string implements TranslatableInterface
{
    case SUMMARY = 'summary';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::SUMMARY => $translator->trans('invoicing_rate_report_view_mode_enum.summary.label', locale: $locale),
        };
    }
}
