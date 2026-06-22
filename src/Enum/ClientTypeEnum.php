<?php

namespace App\Enum;

enum ClientTypeEnum: string
{
    case INTERNAL = 'internal';
    // Legacy value kept for backwards compatibility with existing client rows.
    case EXTERNAL = 'external';
    case EXTERNAL_WITH_MOMS = 'external_with_moms';
    case EXTERNAL_WITHOUT_MOMS = 'external_without_moms';

    public function isExternal(): bool
    {
        return match ($this) {
            self::EXTERNAL, self::EXTERNAL_WITH_MOMS, self::EXTERNAL_WITHOUT_MOMS => true,
            self::INTERNAL => false,
        };
    }

    /**
     * The material number a client type maps to on its invoices.
     *
     * Legacy "external" carries no moms distinction, so it keeps the previous
     * default of "with moms".
     */
    public function toMaterialNumber(): MaterialNumberEnum
    {
        return match ($this) {
            self::INTERNAL => MaterialNumberEnum::INTERNAL,
            self::EXTERNAL_WITHOUT_MOMS => MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS,
            self::EXTERNAL, self::EXTERNAL_WITH_MOMS => MaterialNumberEnum::EXTERNAL_WITH_MOMS,
        };
    }
}
