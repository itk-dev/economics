<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SynchronizationStatusEnum: string implements TranslatableInterface
{
    case NOT_STARTED = 'NOT_STARTED';
    case RUNNING = 'RUNNING';
    case DONE = 'DONE';
    case ERROR = 'ERROR';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::NOT_STARTED => $translator->trans('synchronization_status_enum.not_started', locale: $locale),
            self::RUNNING => $translator->trans('synchronization_status_enum.running', locale: $locale),
            self::DONE => $translator->trans('synchronization_status_enum.done', locale: $locale),
            self::ERROR => $translator->trans('synchronization_status_enum.error', locale: $locale),
        };
    }
}
