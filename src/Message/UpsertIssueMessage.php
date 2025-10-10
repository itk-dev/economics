<?php

namespace App\Message;

use App\Model\Upsert\UpsertIssueData;

readonly class UpsertIssueMessage
{
    public function __construct(
        public UpsertIssueData $issueData,
    ) {
    }
}
