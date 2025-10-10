<?php

namespace App\Message;

use App\Model\Upsert\UpsertProjectData;

readonly class UpsertProjectMessage
{
    public function __construct(
        public UpsertProjectData $projectData,
    ) {
    }
}
