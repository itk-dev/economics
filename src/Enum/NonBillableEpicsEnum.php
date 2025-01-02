<?php

namespace App\Enum;

/*
 * Kind is a term on a worklog in Leantime:
 * https://github.com/Leantime/leantime/blob/80c4542e19692e423820bd9030907070d281571e/app/Domain/Timesheets/Services/Timesheets.php#L22
 * */
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
