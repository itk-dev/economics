<?php

namespace App\Message;

use App\Model\Upsert\UpsertVersionData;

readonly class UpsertVersionMessage
{
    public function __construct(
        public UpsertVersionData $versionData,
    ) {
    }
}
