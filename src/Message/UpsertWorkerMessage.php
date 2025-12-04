<?php

namespace App\Message;

use App\Model\DataProvider\DataProviderWorkerData;

readonly class UpsertWorkerMessage
{
    public function __construct(
        public DataProviderWorkerData $workerData,
    )
    {
    }
}
