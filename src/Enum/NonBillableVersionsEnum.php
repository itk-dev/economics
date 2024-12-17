<?php

namespace App\Enum;

enum NonBillableVersionsEnum: string
{
    case UB = 'UB';
    /**
     * @return array<string,string>
     */
    public static function getAsArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $choices, NonBillableVersionsEnum $type) => $choices + [$type->name => $type->value],
            [],
        );
    }
}
