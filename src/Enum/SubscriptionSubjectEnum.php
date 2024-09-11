<?php

namespace App\Enum;

enum SubscriptionSubjectEnum: string
{
    case HOUR_REPORT = 'hour report';

    /**
     * @return array<string,string>
     */
    public static function getAsArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $choices, SubscriptionSubjectEnum $type) => $choices + [$type->name => $type->value],
            [],
        );
    }
}
