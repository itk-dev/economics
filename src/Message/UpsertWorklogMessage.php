<?php

namespace App\Message;

use App\Model\Upsert\UpsertWorklogData;

readonly class UpsertWorklogMessage
{
    public function __construct(
        public UpsertWorklogData $worklogData,
    ) {}
}
