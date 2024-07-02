<?php

namespace App\Enum;

/*
 * Kind is a term on a worklog in Leantime:
 * https://github.com/Leantime/leantime/blob/80c4542e19692e423820bd9030907070d281571e/app/Domain/Timesheets/Services/Timesheets.php#L22
 * */
enum BillableKindsEnum: string
{
    case GENERAL_BILLABLE = 'GENERAL_BILLABLE';
    case PROJECTMANAGEMENT = 'PROJECTMANAGEMENT';
    case DEVELOPMENT = 'DEVELOPMENT';
    case TESTING = 'TESTING';

    /**
     * @return array<string,string>
     */
    public static function getAsArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $choices, BillableKindsEnum $type) => $choices + [$type->name => $type->value],
            [],
        );
    }
}
