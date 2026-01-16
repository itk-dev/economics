<?php

namespace App\Message;

use App\Model\DataProvider\DataProviderProjectData;

readonly class UpsertProjectMessage
{
    public function __construct(
        public DataProviderProjectData $projectData,
    ) {
    }
}
