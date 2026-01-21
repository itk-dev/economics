<?php

namespace App\Model\Reports;

final class CybersecurityTicketData
{
    /**
     * @param CybersecurityWorklogData[] $worklogs
     */
    public function __construct(
        public int $issueId,
        public string $projectTrackerId,
        public string $headline,
        public float $totalSpent,
        public string $linkToIssue,
        public array $worklogs = [],
    ) {
    }
}
