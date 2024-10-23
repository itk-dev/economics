<?php

namespace App\Enum;

enum SubscriptionFrequencyEnum: string
{
    case FREQUENCY_MONTHLY = 'monthly';
    case FREQUENCY_QUARTERLY = 'quarterly';

    /**
     * @return array<string,string>
     */
    public static function getAsArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $choices, SubscriptionFrequencyEnum $type) => $choices + [$type->name => $type->value],
            [],
        );
    }
}
