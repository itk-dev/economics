<?php

namespace App\Message;

use App\Model\DataProvider\DataProviderIssueData;

readonly class UpsertIssueMessage
{
    public function __construct(
        public DataProviderIssueData $issueData,
    ) {
    }
}
