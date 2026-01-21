<?php

namespace App\Model\Reports;

final class CybersecurityProjectData
{
    /**
     * @var CybersecurityTicketData[]
     */
    public array $tickets = [];

    public function __construct(
        public string $projectName,
        public float $totalSpent = 0.0,
        public bool $hasCybersecurityAgreement = false,
    ) {
    }
}
