<?php

namespace App\Enum;

/*
Structure grabbed from Leantime:
https://github.com/Leantime/leantime/blob/f81a5cbac4377f43719f77f909c79c15a0113f0a/app/Domain/Tickets/Repositories/Tickets.php#L48
*/

enum IssueStatusEnum: string
{
    case READY_FOR_PLANNING = 'ready for planning';
    case NEW = 'new';
    case IN_PROGRESS = 'in progress';
    case WAITING = 'waiting';
    case BLOCKED = 'blocked';
    case IN_REVIEW = 'in review';
    case READY_FOR_RELEASE = 'ready for release';
    case READY_FOR_TEST = 'ready for test';
    case DONE = 'done';
    case ARCHIVED = 'archived';
    case OTHER = 'other';

    /**
     * @return array<string,string>
     */
    public static function getAsArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $choices, IssueStatusEnum $type) => $choices + [$type->name => $type->value],
            [],
        );
    }
}
