<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SynchronizationStepEnum: string implements TranslatableInterface
{
    case PROJECTS = 'PROJECTS';
    case ACCOUNTS = 'ACCOUNTS';
    case ISSUES = 'ISSUES';
    case WORKLOGS = 'WORKLOGS';

    public function trans(TranslatorInterface $translator, string $locale = null): string
    {
        return match ($this) {
            self::PROJECTS => $translator->trans('synchronization_step_enum.projects', locale: $locale),
            self::ACCOUNTS => $translator->trans('synchronization_step_enum.accounts', locale: $locale),
            self::ISSUES => $translator->trans('synchronization_step_enum.issues', locale: $locale),
            self::WORKLOGS => $translator->trans('synchronization_step_enum.worklogs', locale: $locale),
        };
    }
}
