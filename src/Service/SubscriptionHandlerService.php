<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Enum\SubscriptionSubjectEnum;

class SubscriptionHandlerService
{
    public function __construct()
    {
    }

    public function handleSubscription(Subscription $subscription)
    {
        switch($subscription->getSubject()) {
            case SubscriptionSubjectEnum::HOUR_REPORT:

                break;
        }
    }
}
