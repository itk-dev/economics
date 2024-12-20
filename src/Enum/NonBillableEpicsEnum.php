<?php

namespace App\Enum;

enum NonBillableEpicsEnum: string
{
    case UB = 'UB';

    /**
     * @return array<string,string>
     */
    public static function getAsArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $choices, NonBillableEpicsEnum $type) => $choices + [$type->name => $type->value],
            [],
        );
    }
}
