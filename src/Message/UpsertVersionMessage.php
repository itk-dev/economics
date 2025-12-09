<?php

namespace App\Message;

use App\Model\DataProvider\DataProviderVersionData;

readonly class UpsertVersionMessage
{
    public function __construct(
        public DataProviderVersionData $versionData,
    ) {
    }
}
