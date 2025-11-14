<?php

namespace App\Message;

use App\Model\DataProvider\DataProviderWorklogData;

readonly class UpsertWorklogMessage
{
    public function __construct(
        public DataProviderWorklogData $worklogData,
    ) {
    }
}
